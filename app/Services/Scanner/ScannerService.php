<?php

namespace App\Services\Scanner;

use App\Jobs\ProcessScannerSignal;
use App\Models\BotTrade;
use App\Models\ExchangeAccount;
use App\Models\GlobalSetting;
use App\Models\ScannerConfig;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ScannerService
{
    protected MarketDiscoveryService $discovery;
    protected MarketFilterService $filter;
    protected TechnicalAnalyzerService $analyzer;
    protected SignalScorer $scorer;
    protected SignalRepository $repository;
    protected ScannerRiskEngine $risk;

    public function __construct(
        MarketDiscoveryService $discovery,
        MarketFilterService $filter,
        TechnicalAnalyzerService $analyzer,
        SignalScorer $scorer,
        SignalRepository $repository,
        ScannerRiskEngine $risk
    ) {
        $this->discovery = $discovery;
        $this->filter = $filter;
        $this->analyzer = $analyzer;
        $this->scorer = $scorer;
        $this->repository = $repository;
        $this->risk = $risk;
    }

    public function run(User $user): array
    {
        $config = ScannerConfig::forUser($user->id);

        if ($config->status !== 'running') {
            $message = $config->status === 'paused'
                ? 'Trading is paused: '.($config->paused_reason ?: 'waiting for manual resume').' — resume from the scanner page.'
                : 'Scanner is stopped.';
            return ['ok' => false, 'message' => $message, 'signals' => []];
        }
        if (GlobalSetting::get('scanner_kill_switch', false)) {
            return ['ok' => false, 'message' => 'Scanner kill switch is ON.', 'signals' => []];
        }

        $start = microtime(true);

        // Capital protection: sync equity peak and auto-pause on breach.
        if ($this->applyRiskProtection($user, $config)) {
            return ['ok' => false, 'message' => 'Trading auto-paused by risk protection.', 'signals' => []];
        }

        $this->repository->expireDue();

        $accounts = $this->accounts($config);
        if (empty($accounts)) {
            return ['ok' => false, 'message' => 'No connected exchange accounts to scan.', 'signals' => []];
        }

        $qualified = [];
        $rejected = 0;
        $scanned = 0;

        foreach ($accounts as $account) {
            foreach ($this->marketTypes($config) as $marketType) {
                $result = $this->scanAccount($account, $marketType, $config, $qualified);
                $scanned += $result['scanned'];
                $rejected += $result['rejected'];
                $qualified = $result['qualified'];
            }
        }

        $duration = (int) round((microtime(true) - $start) * 1000);

        $config->scanned_markets = (int) $config->scanned_markets + $scanned;
        $config->qualified_signals = (int) $config->qualified_signals + count($qualified);
        $config->last_scan_at = now();
        $config->last_scan_duration_ms = $duration;
        $config->save();

        ActivityLogger::log(
            $user->id,
            'scan.completed',
            "Scanned {$scanned} markets, qualified ".count($qualified)." signals, rejected {$rejected}.",
            count($qualified) > 0 ? 'success' : 'info',
            $config->id,
            null,
            ['scanned' => $scanned, 'qualified' => count($qualified), 'rejected' => $rejected, 'duration_ms' => $duration]
        );

        return [
            'ok' => true,
            'message' => "Scanned {$scanned} markets, qualified ".count($qualified)." signals, rejected {$rejected}.",
            'signals' => $qualified,
            'scanned' => $scanned,
            'rejected' => $rejected,
            'duration_ms' => $duration,
        ];
    }

    /**
     * Update the equity peak and auto-pause the scanner when the drawdown or
     * daily-loss capital-protection limit is breached. Returns true if paused.
     */
    protected function applyRiskProtection(User $user, ScannerConfig $config): bool
    {
        $equity = $this->risk->userEquity($user->id);
        if ($equity <= 0) {
            return false;
        }

        $peak = (float) $config->peak_equity;
        if ($peak <= 0 || $equity > $peak) {
            $config->peak_equity = $equity;
            $config->save();
            return false;
        }

        $maxDrawdown = (float) GlobalSetting::get('global_max_drawdown', 15.0);
        $drawdown = ($peak - $equity) / $peak * 100;

        $todayLoss = (float) BotTrade::query()
            ->where('user_id', $user->id)
            ->whereNotNull('closed_at')
            ->where('closed_at', '>=', now()->startOfDay())
            ->whereNotNull('pnl')
            ->sum('pnl');
        $dailyBreach = $todayLoss < -($equity * ((float) $config->max_daily_loss / 100));

        if ($drawdown >= $maxDrawdown || $dailyBreach) {
            $reason = $drawdown >= $maxDrawdown
                ? 'Drawdown limit reached ('.round($drawdown, 1).'%)'
                : 'Daily loss limit reached ('.round($todayLoss, 2).')';
            $config->status = 'paused';
            $config->paused_reason = $reason.' — resume manually';
            $config->save();
            Log::warning('scanner.auto_paused', [
                'user' => $user->id, 'reason' => $config->paused_reason,
            ]);
            ActivityLogger::log($user->id, 'risk.auto_paused', $reason, 'danger', $config->id);
            return true;
        }

        return false;
    }

    protected function scanAccount(ExchangeAccount $account, string $marketType, ScannerConfig $config, array &$qualified): array
    {
        $markets = $this->discovery->markets($account, $marketType);
        $candidates = $this->filter->structuralFilter($markets, $config);
        $scanned = count($candidates);
        $rejected = 0;

        if (empty($candidates)) {
            return ['scanned' => $scanned, 'rejected' => $rejected, 'qualified' => $qualified];
        }

        $tickers = $this->filter->tickers($account, $marketType);
        $profiled = $this->filter->liquidityProfile($candidates, $tickers);
        $profiled = $this->filter->applyLiquidityFilters($profiled, $config);
        $profiled = $this->filter->capMarkets($profiled, $config);

        foreach ($profiled as $p) {
            $market = $p['market'];
            $symbol = $market['symbol'];

            $analysis = $this->analyze($account, $symbol, $p['ticker'], $config, $marketType);
            if ($analysis === null) {
                ++$rejected;
                continue;
            }

            // Volatility gate.
            if ($this->analyzer->volatilityRank($analysis['volatility_class']) > $this->analyzer->volatilityRank($config->max_volatility)) {
                $this->repository->createRejected(
                    $account->user_id, $config->id,
                    $this->accountMap($account), $account->exchange, $symbol,
                    strtoupper($market['base_asset'] ?? ''), strtoupper($market['quote_asset'] ?? ''),
                    $marketType, $analysis['bias']['direction'] ?? 'neutral', $analysis['primary'],
                    'technical', (float) $analysis['close'], (int) $analysis['score'],
                    'Volatility above configured maximum ('.$analysis['volatility_class'].')', $analysis
                );
                ++$rejected;
                continue;
            }

            if ($analysis['bias']['direction'] === 'neutral' || $analysis['score'] < (int) $config->min_signal_score) {
                if ($analysis['score'] > 0) {
                    $this->repository->createRejected(
                        $account->user_id, $config->id,
                        $this->accountMap($account), $account->exchange, $symbol,
                        strtoupper($market['base_asset'] ?? ''), strtoupper($market['quote_asset'] ?? ''),
                        $marketType, $analysis['bias']['direction'] ?? 'neutral', $analysis['primary'],
                        'technical', (float) $analysis['close'], (int) $analysis['score'],
                        'Score below minimum of '.$config->min_signal_score, $analysis
                    );
                    ++$rejected;
                } else {
                    $this->repository->createRejected(
                        $account->user_id, $config->id,
                        $this->accountMap($account), $account->exchange, $symbol,
                        strtoupper($market['base_asset'] ?? ''), strtoupper($market['quote_asset'] ?? ''),
                        $marketType, 'neutral', $analysis['primary'],
                        'technical', (float) $analysis['close'], 0,
                        'No directional edge on primary timeframe', $analysis
                    );
                    ++$rejected;
                }
                continue;
            }

            // AI + RR gates.
            $ai = $analysis['ai']['probability'] ?? 0;
            if ($ai < (float) $config->min_ai_probability || $analysis['risk_reward'] < (float) $config->min_risk_reward) {
                $reason = $ai < (float) $config->min_ai_probability
                    ? 'AI probability below '.$config->min_ai_probability.'%'
                    : 'Risk/reward below '.$config->min_risk_reward;
                $this->repository->createRejected(
                    $account->user_id, $config->id,
                    $this->accountMap($account), $account->exchange, $symbol,
                    strtoupper($market['base_asset'] ?? ''), strtoupper($market['quote_asset'] ?? ''),
                    $marketType, $analysis['bias']['direction'], $analysis['primary'],
                    'technical', (float) $analysis['close'], (int) $analysis['score'], $reason, $analysis
                );
                ++$rejected;
                continue;
            }

            $fingerprint = $this->repository->fingerprint($account->exchange, $symbol, $analysis['bias']['direction'], $analysis['primary']);
            if ($this->repository->isDuplicate($fingerprint)) {
                continue;
            }

            $signal = $this->repository->create(
                $account->user_id, $config->id,
                $this->accountMap($account), $account->exchange, $symbol,
                strtoupper($market['base_asset'] ?? ''), strtoupper($market['quote_asset'] ?? ''),
                $marketType, $analysis['bias']['direction'], $analysis['primary'],
                'technical', (float) $analysis['entry'], (float) $analysis['stop'],
                (float) $analysis['target'], (float) $analysis['risk_reward'],
                (int) $analysis['score'], (string) $analysis['quality'], $analysis, $config
            );

            $qualified[] = $signal;

            // Auto-execution path (paper + live) — only when risk engine passes.
            if ($config->auto_trading) {
                $risk = $this->risk->evaluate($account, [
                    'symbol' => $symbol,
                    'base_asset' => $market['base_asset'] ?? '',
                    'entry_price' => (float) $analysis['entry'],
                    'current_price' => (float) $analysis['close'],
                    'stop_loss' => (float) $analysis['stop'],
                    'direction' => $analysis['bias']['direction'],
                    'market_type' => $marketType,
                    'leverage_limit' => $market['leverage_limit'] ?? null,
                ], $config);

                if ($risk['pass']) {
                    ProcessScannerSignal::dispatch($signal->id);
                    ActivityLogger::log(
                        $account->user_id, 'signal.auto_submitted',
                        "Auto-trade submitted for {$symbol}.",
                        'info', $config->id, $signal->id
                    );
                } else {
                    $this->repository->markWatchlist($signal);
                    Log::info('scanner.auto.blocked', [
                        'signal' => $signal->id, 'failures' => $risk['failures'],
                    ]);
                    ActivityLogger::log(
                        $account->user_id, 'signal.auto_blocked',
                        "Auto-trade blocked for {$symbol}: ".implode('; ', $risk['failures']),
                        'warning', $config->id, $signal->id
                    );
                }
            }
        }

        return ['scanned' => $scanned, 'rejected' => $rejected, 'qualified' => $qualified];
    }

    protected function analyze(ExchangeAccount $account, string $symbol, array $ticker, ScannerConfig $config, string $marketType): ?array
    {
        $snapshot = $this->analyzer->analyzeMarket($account, $symbol, $config->timeframeList());
        if (empty($snapshot['timeframes'])) return null;

        $a = $snapshot['primary_analysis'];
        $close = (float) $snapshot['close'];
        $atr = (float) $snapshot['atr'];
        if ($close <= 0 || $atr <= 0) return null;

        $direction = $snapshot['bias']['direction'];
        $long = $direction === 'long';
        $atrMultiplier = 1.5;
        $stop = $long ? $close - ($atr * $atrMultiplier) : $close + ($atr * $atrMultiplier);
        $slDist = abs($close - $stop);

        $minRr = (float) $config->min_risk_reward;
        $target = $long ? $close + ($slDist * $minRr) : $close - ($slDist * $minRr);

        // Respect structure resistance/support to avoid overreach.
        $pivotHi = (float) $snapshot['pivot_high'];
        $pivotLo = (float) $snapshot['pivot_low'];
        if ($long && $pivotHi > $close && $pivotHi < $target) {
            $candidate = $pivotHi * 0.998;
            $rr = ($candidate - $close) / $slDist;
            if ($rr >= $minRr) $target = $candidate;
        }
        if (!$long && $pivotLo > 0 && $pivotLo > $close && $pivotLo > $target) {
            $candidate = $pivotLo * 1.002;
            $rr = ($close - $candidate) / $slDist;
            if ($rr >= $minRr) $target = $candidate;
        }

        $rr = abs($target - $close) / $slDist;

        $quoteVolume = (float) ($ticker['quote_volume'] ?? 0);
        if ($quoteVolume <= 0) {
            $quoteVolume = (float) $close * (float) ($ticker['volume'] ?? 0);
        }

        $scored = $this->scorer->score($snapshot, $quoteVolume, $config);
        $score = $scored['score'];
        $direction = $scored['direction'];
        if ($scored['direction'] === 'neutral') return null;

        $snapshot['ai'] = $scored['ai'];
        $snapshot['breakdown'] = $scored['breakdown'];
        $snapshot['entry'] = $close;
        $snapshot['stop'] = $stop;
        $snapshot['target'] = round($target, $this->pricePrecisionFor($symbol));
        $snapshot['risk_reward'] = round($rr, 2);
        $snapshot['score'] = $score;
        $snapshot['quality'] = $this->qualityBand($score);
        $snapshot['volume_24h'] = $quoteVolume;
        $snapshot['spread_pct'] = $ticker['spread_pct'] ?? $this->spreadFromTicker($ticker);

        return $snapshot;
    }

    protected function accounts(ScannerConfig $config): array
    {
        $query = ExchangeAccount::query()
            ->where('user_id', $config->user_id)
            ->where('status', 'connected');

        if (!empty($config->exchanges)) {
            $query->whereIn('exchange', $config->exchanges);
        }

        return $query->get()->all();
    }

    protected function marketTypes(ScannerConfig $config): array
    {
        return $config->market_type === 'both'
            ? ['spot', 'futures']
            : [$config->market_type];
    }

    protected function qualityBand(int $score): string
    {
        if ($score >= 90) return 'exceptional';
        if ($score >= 80) return 'strong';
        if ($score >= 70) return 'good';
        if ($score >= 60) return 'weak';
        return 'reject';
    }

    protected function accountMap(ExchangeAccount $account): array
    {
        return ['id' => $account->id];
    }

    protected function pricePrecisionFor(string $symbol): int
    {
        if (preg_match('/(0\.0000[0-9]*)$/', $symbol)) return 8;
        return 6;
    }

    protected function spreadFromTicker(array $ticker): float
    {
        $bid = (float) ($ticker['bid'] ?? 0);
        $ask = (float) ($ticker['ask'] ?? 0);
        if ($bid <= 0 || $ask <= 0) return 0;
        return (($ask - $bid) / (($bid + $ask) / 2)) * 100;
    }
}