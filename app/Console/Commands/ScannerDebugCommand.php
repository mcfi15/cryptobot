<?php

namespace App\Console\Commands;

use App\Models\ExchangeAccount;
use App\Models\ScannerConfig;
use App\Models\User;
use App\Services\Scanner\MarketDiscoveryService;
use App\Services\Scanner\MarketFilterService;
use App\Services\Scanner\SignalRepository;
use App\Services\Scanner\SignalScorer;
use App\Services\Scanner\TechnicalAnalyzerService;
use Illuminate\Console\Command;

class ScannerDebugCommand extends Command
{
    protected $signature = 'scanner:debug
        {--user= : User ID (defaults to first user)}
        {--limit= : Max markets to deep-analyze (default: config max_markets)}
        {--detailed : Show per-market rows}
        {--apply : Apply recommended thresholds to the user config}
        {--json : Output machine-readable JSON}';

    protected $description = 'Read-only diagnostic: per-gate rejection counts and threshold recommendations';

    public function handle(
        MarketDiscoveryService $discovery,
        MarketFilterService $filter,
        TechnicalAnalyzerService $analyzer,
        SignalScorer $scorer,
        SignalRepository $repository,
    ): int {
        $isJson = $this->option('json');

        // ── Resolve user ──────────────────────────────────────────────
        $userId = $this->option('user');
        $user = $userId
            ? User::find((int) $userId)
            : User::first();

        if (!$user) {
            return $this->exit($isJson, 1, 'error', 'No users found.');
        }

        $config = ScannerConfig::where('user_id', $user->id)->first();
        if (!$config) {
            return $this->exit($isJson, 1, 'error', 'No scanner config for user.');
        }

        // ── Connected accounts ────────────────────────────────────────
        $accounts = ExchangeAccount::where('user_id', $user->id)
            ->where('status', 'connected')
            ->get()
            ->all();

        if (empty($accounts)) {
            return $this->exit($isJson, 0, 'warning', 'No connected exchange accounts.', [
                'config' => $this->configSummary($config),
            ]);
        }

        // ── Thresholds ────────────────────────────────────────────────
        $minScore = (int) $config->min_signal_score;
        $minAi = (float) $config->min_ai_probability;
        $minRr = (float) $config->min_risk_reward;
        $maxVol = (string) $config->max_volatility;

        // ── Per-account / market-type scanning (read-only) ────────────
        $marketTypes = $config->market_type === 'both'
            ? ['spot', 'futures']
            : [$config->market_type];

        $totals = [
            'discovered' => 0,
            'structural_pass' => 0,
            'missing_ticker' => 0,
            'low_volume' => 0,
            'wide_spread' => 0,
            'liquid' => 0,
            'capped' => 0,
            'analyzed' => 0,
            'analysis_fail' => 0,
            'neutral' => 0,
            'volatility_rejected' => 0,
            'score_rejected' => 0,
            'ai_rejected' => 0,
            'rr_rejected' => 0,
            'duplicate' => 0,
            'qualified' => 0,
        ];
        $rows = [];
        $limit = $this->option('limit') !== null
            ? (int) $this->option('limit')
            : (int) $config->max_markets;
        $analyzedCount = 0;

        foreach ($accounts as $account) {
            foreach ($marketTypes as $mt) {
                $discovered = $discovery->markets($account, $mt);
                $totals['discovered'] += count($discovered);

                $candidates = $filter->structuralFilter($discovered, $config);
                $totals['structural_pass'] += count($candidates);

                $tickerMap = $filter->tickers($account, $mt);
                $profiled = $filter->liquidityProfile($candidates, $tickerMap);
                $totals['missing_ticker'] += count($candidates) - count($profiled);

                foreach ($profiled as $p) {
                    if ($p['quote_volume'] < (float) $config->min_volume_24h) {
                        $totals['low_volume']++;
                    }
                    $maxSpread = (float) $config->max_spread_pct;
                    if ($maxSpread > 0 && $p['spread_pct'] > $maxSpread) {
                        $totals['wide_spread']++;
                    }
                }

                $liquid = $filter->applyLiquidityFilters($profiled, $config);
                $totals['liquid'] += count($liquid);

                $capped = $filter->capMarkets($liquid, $config);
                $totals['capped'] += count($capped);

                foreach ($capped as $p) {
                    if ($limit > 0 && $analyzedCount >= $limit) {
                        break;
                    }
                    $analyzedCount++;

                    $symbol = $p['market']['symbol'];

                    try {
                        $snapshot = $analyzer->analyzeMarket(
                            $account,
                            $symbol,
                            $config->timeframeList(),
                        );
                    } catch (\Throwable) {
                        $snapshot = ['timeframes' => []];
                    }

                    if (empty($snapshot['timeframes'])) {
                        $totals['analysis_fail']++;
                        $this->record($rows, $symbol, $account, $mt, 'analysis_fail', 0, 0, 0, $snapshot);
                        continue;
                    }

                    $close = (float) ($snapshot['close'] ?? 0);
                    $atr = (float) ($snapshot['atr'] ?? 0);
                    if ($close <= 0 || $atr <= 0) {
                        $totals['analysis_fail']++;
                        $this->record($rows, $symbol, $account, $mt, 'analysis_fail', 0, 0, 0, $snapshot);
                        continue;
                    }

                    $quoteVolume = (float) ($p['quote_volume'] ?? 0);
                    if ($quoteVolume <= 0) {
                        $quoteVolume = $close * (float) ($p['ticker']['volume'] ?? 0);
                    }

                    $scored = $scorer->score($snapshot, $quoteVolume, $config);
                    $score = (int) ($scored['score'] ?? 0);
                    $direction = $scored['direction'] ?? 'neutral';
                    $ai = (float) ($scored['ai']['probability'] ?? 0);

                    // RR from pipeline (always ≈ min_risk_reward; included for completeness).
                    $atrMul = 1.5;
                    $slDist = $atr * $atrMul;
                    $long = $direction === 'long';
                    $target = $long ? $close + $slDist * $minRr : $close - $slDist * $minRr;
                    $rr = $slDist > 0 ? abs($target - $close) / $slDist : 0;

                    if ($direction === 'neutral' || $score === 0) {
                        $totals['neutral']++;
                        $this->record($rows, $symbol, $account, $mt, 'neutral', $score, $ai, $rr, $snapshot, $direction, 'no directional edge');
                        continue;
                    }

                    // Volatility gate.
                    $volRank = $analyzer->volatilityRank($snapshot['volatility_class'] ?? 'normal');
                    if ($volRank > $analyzer->volatilityRank($maxVol)) {
                        $totals['volatility_rejected']++;
                        $this->record($rows, $symbol, $account, $mt, 'volatility', $score, $ai, $rr, $snapshot, $direction, 'volatility '.$snapshot['volatility_class']);
                        continue;
                    }

                    // Score gate.
                    if ($score < $minScore) {
                        $totals['score_rejected']++;
                        $this->record($rows, $symbol, $account, $mt, 'score', $score, $ai, $rr, $snapshot, $direction, 'score '.$score.' < '.$minScore);
                        continue;
                    }

                    // AI gate.
                    if ($ai < $minAi) {
                        $totals['ai_rejected']++;
                        $this->record($rows, $symbol, $account, $mt, 'ai', $score, $ai, $rr, $snapshot, $direction, 'ai '.$ai.'% < '.$minAi.'%');
                        continue;
                    }

                    // RR gate (nearly never fires — target derived from min_risk_reward).
                    if ($rr < $minRr) {
                        $totals['rr_rejected']++;
                        $this->record($rows, $symbol, $account, $mt, 'rr', $score, $ai, $rr, $snapshot, $direction, 'rr '.round($rr, 2).' < '.round($minRr, 2));
                        continue;
                    }

                    // Duplicate check.
                    $fp = $repository->fingerprint(
                        $account->exchange,
                        $symbol,
                        $direction,
                        $config->timeframeList()[0] ?? '4h',
                    );
                    if ($repository->isDuplicate($fp)) {
                        $totals['duplicate']++;
                        $this->record($rows, $symbol, $account, $mt, 'duplicate', $score, $ai, $rr, $snapshot, $direction, 'duplicate');
                        continue;
                    }

                    $totals['qualified']++;
                    $this->record($rows, $symbol, $account, $mt, 'qualified', $score, $ai, $rr, $snapshot, $direction, 'PASS');
                }
            }
        }

        // ── Stats ─────────────────────────────────────────────────────
        $directional = array_values(array_filter($rows, fn ($r) => in_array($r['gate'], ['qualified', 'score', 'ai', 'rr', 'duplicate', 'volatility'])));
        $scores = array_column($directional, 'score');
        $ais = array_column($directional, 'ai');
        $rrs = array_column($directional, 'rr');
        sort($scores);
        sort($ais);
        sort($rrs);

        $stats = [
            'score' => $this->quantileStats($scores),
            'ai' => $this->quantileStats($ais),
            'rr' => $this->quantileStats($rrs),
            'directional_count' => count($directional),
        ];

        // ── Recommendation ────────────────────────────────────────────
        $rec = self::recommendThresholds($directional);
        $wouldQualify = 0;
        if ($rec && count($directional) > 0) {
            foreach ($directional as $r) {
                if ($r['score'] >= $rec['suggested_score'] && $r['ai'] >= $rec['suggested_ai']) {
                    $wouldQualify++;
                }
            }
        }

        // ── Apply ─────────────────────────────────────────────────────
        $applied = null;
        if ($this->option('apply') && $rec) {
            $changed = [];
            if ((int) $config->min_signal_score !== $rec['suggested_score']) {
                $changed['min_signal_score'] = $rec['suggested_score'];
            }
            if ((float) $config->min_ai_probability !== $rec['suggested_ai']) {
                $changed['min_ai_probability'] = $rec['suggested_ai'];
            }
            if (!empty($changed)) {
                $config->fill($changed);
                $config->save();
                $applied = $changed;
            }
        }

        // ── Output ────────────────────────────────────────────────────
        if ($isJson) {
            $this->line(json_encode([
                'config' => $this->configSummary($config),
                'funnel' => $totals,
                'stats' => $stats,
                'recommendation' => $rec,
                'would_qualify' => $wouldQualify,
                'applied' => $applied,
                'rows' => $this->option('detailed') ? $rows : array_filter($rows, fn ($r) => $r['gate'] === 'qualified'),
            ], JSON_PRETTY_PRINT));
            return 0;
        }

        $this->printHeader($user, $config, $accounts);
        $this->printFunnel($totals);
        $this->printStats($stats);
        $this->printRecommendation($rec, $wouldQualify, $config, $applied);

        if ($this->option('detailed') || $totals['qualified'] > 0) {
            $this->printRows($rows, $totals['qualified'] > 0);
        }

        return 0;
    }

    // ── Static: recommendation logic (testable) ──────────────────────────

    public static function recommendThresholds(array $directional): ?array
    {
        if (empty($directional)) {
            return ['suggested_score' => 55, 'suggested_ai' => 55.0, 'reason' => 'no directional candidates; using safe defaults', 'would_qualify' => 0];
        }

        $scores = array_column($directional, 'score');
        $ais = array_column($directional, 'ai');
        sort($scores);
        sort($ais);
        $n = count($scores);

        $scoreCut = (int) round(self::percentile($scores, 0.78));
        $aiCut = (float) round(self::percentile($ais, 0.65), 2);

        $scoreCut = max(50, min($scoreCut, 75));
        $aiCut = max(50.0, min($aiCut, 68.0));

        return [
            'suggested_score' => $scoreCut,
            'suggested_ai' => $aiCut,
            'reason' => 'based on '.count($directional).' directional candidates (target ~8% admit rate)',
            'would_qualify' => 0,
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    protected function record(
        array &$rows,
        string $symbol,
        ExchangeAccount $account,
        string $mt,
        string $gate,
        int $score,
        float $ai,
        float $rr,
        array $snapshot,
        string $direction = '',
        string $reason = '',
    ): void {
        $rows[] = [
            'symbol' => $symbol,
            'exchange' => $account->exchange,
            'market_type' => $mt,
            'gate' => $gate,
            'score' => $score,
            'ai' => round($ai, 2),
            'rr' => round($rr, 2),
            'direction' => $direction,
            'volatility' => $snapshot['volatility_class'] ?? '',
            'reason' => $reason,
        ];
    }

    protected function configSummary(ScannerConfig $c): array
    {
        return [
            'min_signal_score' => (int) $c->min_signal_score,
            'min_ai_probability' => (float) $c->min_ai_probability,
            'min_risk_reward' => (float) $c->min_risk_reward,
            'min_volume_24h' => (float) $c->min_volume_24h,
            'max_spread_pct' => (float) $c->max_spread_pct,
            'max_volatility' => $c->max_volatility,
            'max_markets' => (int) $c->max_markets,
            'status' => $c->status,
            'trading_mode' => $c->trading_mode,
        ];
    }

    protected function quantileStats(array $values): array
    {
        if (empty($values)) {
            return ['min' => 0, 'max' => 0, 'avg' => 0, 'median' => 0, 'p25' => 0, 'p75' => 0];
        }
        return [
            'min' => round($values[0], 2),
            'max' => round(end($values), 2),
            'avg' => round(array_sum($values) / count($values), 2),
            'median' => round(self::percentile($values, 0.5), 2),
            'p25' => round(self::percentile($values, 0.25), 2),
            'p75' => round(self::percentile($values, 0.75), 2),
        ];
    }

    public static function percentile(array $sorted, float $p): float
    {
        $n = count($sorted);
        if ($n === 0) return 0;
        if ($n === 1) return $sorted[0];
        $idx = $p * ($n - 1);
        $low = (int) floor($idx);
        $high = (int) ceil($idx);
        if ($low === $high) return $sorted[$low];
        $frac = $idx - $low;
        return $sorted[$low] * (1 - $frac) + $sorted[$high] * $frac;
    }

    // ── Formatting ───────────────────────────────────────────────────

    protected function printHeader(User $user, ScannerConfig $config, array $accounts): void
    {
        $this->newLine();
        $this->line('<info>═══ scanner:debug ═══</info>');
        $this->line("  User:       {$user->email} (#{$user->id})");
        $this->line("  Accounts:   ".implode(', ', array_map(fn ($a) => "{$a->exchange} #{$a->id}", $accounts)));
        $this->line("  Status:     {$config->status} | {$config->trading_mode}");
        $this->line("  Thresholds: score≥{$config->min_signal_score}  AI≥{$config->min_ai_probability}%  RR≥{$config->min_risk_reward}  vol≥{$config->min_volume_24h}  max_spread≤{$config->max_spread_pct}%  vol≤{$config->max_volatility}");
        $this->line("  Limits:     max_markets={$config->max_markets}  timeframes=[".implode(',', $config->timeframeList())."]");
        $this->newLine();
    }

    protected function printFunnel(array $t): void
    {
        $this->line('<info>Pipeline Funnel</info>');
        $this->line('  Discovered markets       : '.number_format($t['discovered']));
        $this->line('  Structural pass          : '.number_format($t['structural_pass']));
        $this->line('  Missing ticker           : '.number_format($t['missing_ticker']));
        $this->line('  Low volume               : '.number_format($t['low_volume']));
        $this->line('  Wide spread              : '.number_format($t['wide_spread']));
        $this->line('  Liquid (volume+spread)   : '.number_format($t['liquid']));
        $this->line('  Capped (max_markets)     : '.number_format($t['capped']));
        $this->line('  Analyzed                 : '.number_format($t['analyzed']));
        $this->newLine();
        $this->line('<info>Rejection Breakdown</info>');
        $this->line('  Analysis failure         : '.number_format($t['analysis_fail']));
        $this->line('  Neutral / no edge        : '.number_format($t['neutral']));
        $this->line('  Volatility               : '.number_format($t['volatility_rejected']));
        $this->line('  Score below minimum      : '.number_format($t['score_rejected']));
        $this->line('  AI below minimum         : '.number_format($t['ai_rejected']));
        $this->line('  RR below minimum         : '.number_format($t['rr_rejected']));
        $this->line('  Duplicate                : '.number_format($t['duplicate']));
        $this->line('  <info>Qualified            : '.number_format($t['qualified']).'</info>');
        $this->newLine();
    }

    protected function printStats(array $stats): void
    {
        if ($stats['directional_count'] === 0) {
            $this->line('<comment>No directional candidates to profile.</comment>');
            $this->newLine();
            return;
        }
        $this->line('<info>Score Distribution  (n='.$stats['directional_count'].')</info>');
        $this->line("  min={$stats['score']['min']}  avg={$stats['score']['avg']}  med={$stats['score']['median']}  p75={$stats['score']['p75']}  max={$stats['score']['max']}");
        $this->line("<info>AI Probability Distribution</info>");
        $this->line("  min={$stats['ai']['min']}%  avg={$stats['ai']['avg']}%  med={$stats['ai']['median']}%  p75={$stats['ai']['p75']}%  max={$stats['ai']['max']}%");
        $this->newLine();
    }

    protected function printRecommendation(?array $rec, int $wouldQualify, ScannerConfig $config, ?array $applied): void
    {
        if (!$rec) return;
        $this->line('<info>Recommendation</info>');
        $this->line("  {$rec['reason']}");
        if ((int) $config->min_signal_score !== $rec['suggested_score'] || (float) $config->min_ai_probability !== $rec['suggested_ai']) {
            $this->line("  <comment>min_signal_score: {$config->min_signal_score} → {$rec['suggested_score']}</comment>");
            $this->line("  <comment>min_ai_probability: {$config->min_ai_probability} → {$rec['suggested_ai']}%</comment>");
            $this->line("  <info>Would qualify with adjusted thresholds: ~{$wouldQualify}</info>");
        } else {
            $this->line("  Thresholds already at recommended levels.");
        }
        if ($applied) {
            $this->line("  <info>Applied: ".json_encode($applied).'</info>');
        }
        $this->newLine();
    }

    protected function printRows(array $rows, bool $hasQualified): void
    {
        $header = ['Symbol', 'Exchange', 'Type', 'Gate', 'Score', 'AI%', 'RR', 'Dir', 'Vol', 'Reason'];
        $displayRows = [];
        if ($hasQualified) {
            $displayRows = array_merge(
                array_values(array_filter($rows, fn ($r) => $r['gate'] === 'qualified')),
                array_values(array_filter($rows, fn ($r) => $r['gate'] !== 'qualified')),
            );
        } else {
            $displayRows = $rows;
        }
        foreach ($displayRows as $r) {
            $this->table([], [[$r['symbol'], $r['exchange'], $r['market_type'], $r['gate'], $r['score'], $r['ai'], $r['rr'], $r['direction'], $r['volatility'], $r['reason']]]);
        }
        $this->newLine();
    }

    protected function exit(bool $isJson, int $code, string $level, string $message, array $extra = []): int
    {
        if ($isJson) {
            $this->line(json_encode(array_merge([
                'status' => $level,
                'message' => $message,
            ], $extra), JSON_PRETTY_PRINT));
            return $code;
        }
        $level === 'error'
            ? $this->error($message)
            : $this->warn($message);
        if (!empty($extra)) {
            $this->line(json_encode($extra, JSON_PRETTY_PRINT));
        }
        return $code;
    }
}
