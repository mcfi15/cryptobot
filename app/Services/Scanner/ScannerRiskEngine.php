<?php

namespace App\Services\Scanner;

use App\Models\BotPosition;
use App\Models\BotTrade;
use App\Models\ExchangeAccount;
use App\Models\GlobalSetting;
use App\Models\ScannerConfig;
use App\Services\Exchanges\ExchangeFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Final gate before ANY scanner execution (manual or automatic).
 * A failing check must never be bypassed for live/auto trading.
 *
 * Gates (in priority): emergency stop, kill switches, pause/drawdown,
 * global caps, exposure (including correlated cluster), daily loss,
 * cooldown, consecutive losses, balance, futures liquidation safety, stale price.
 */
class ScannerRiskEngine
{
    public function evaluate(ExchangeAccount $account, array $tradeContext, ScannerConfig $config, bool $manual = false): array
    {
        $failures = [];

        if (GlobalSetting::get('emergency_stop', false)) {
            $failures[] = 'Emergency stop is active — all trading suspended';
        }
        if (GlobalSetting::get('global_trading_kill_switch', false)) {
            $failures[] = 'Global trading kill switch is ON';
        }
        if (GlobalSetting::get('scanner_kill_switch', false)) {
            $failures[] = 'Scanner kill switch is ON';
        }
        if (!$manual && $config->status !== 'running') {
            $failures[] = $config->status === 'paused'
                ? 'Trading is paused: '.($config->paused_reason ?: 'waiting for manual resume')
                : 'Scanner is stopped';
        }
        if ($config->trading_mode === 'live' && !$config->paper_mode) {
            if (!GlobalSetting::get('scanner_live_allowed', false)) {
                $failures[] = 'Live trading not enabled by admin';
            }
            if (!$account->trading_enabled) {
                $failures[] = 'Exchange account trading is disabled';
            }
        }
        if (!$account->trading_enabled && !$config->paper_mode) {
            $failures[] = 'Exchange account trading disabled';
        }

        $failures = array_merge($failures, $this->globalCapsCheck($config));
        $failures = array_merge($failures, $this->drawdownCheck($account->user_id, $config));

        $open = $this->openPositions($account->user_id);
        $failures = array_merge($failures, $this->exposureChecks($account, $tradeContext, $config, $open));
        $failures = array_merge($failures, $this->correlationCheck($account, $tradeContext, $config, $open));
        $failures = array_merge($failures, $this->dailyLossCheck($account->user_id, $config));
        $failures = array_merge($failures, $this->cooldownCheck($account->user_id, $tradeContext, $config));
        $failures = array_merge($failures, $this->consecutiveLossCheck($account->user_id, $config));
        $failures = array_merge($failures, $this->liquidationCheck($account, $tradeContext, $config));
        $failures = array_merge($failures, $this->balanceCheck($account));
        $failures = array_merge($failures, $this->stalePriceCheck($tradeContext));

        return [
            'pass' => empty($failures),
            'failures' => $failures,
        ];
    }

    /**
     * Global admin caps are the ceiling above per-user config; a config that
     * exceeds them is a misconfiguration and fails closed.
     */
    protected function globalCapsCheck(ScannerConfig $config): array
    {
        $failures = [];

        $maxRisk = (float) GlobalSetting::get('max_risk_per_trade', 5.0);
        if ((float) $config->risk_per_trade > $maxRisk) {
            $failures[] = "Risk per trade {$config->risk_per_trade}% exceeds global cap {$maxRisk}%";
        }

        $maxLeverage = (int) GlobalSetting::get('max_leverage', 20);
        if ((int) $config->max_leverage > $maxLeverage) {
            $failures[] = "Max leverage {$config->max_leverage}x exceeds global cap {$maxLeverage}x";
        }

        $maxPositions = (int) GlobalSetting::get('max_open_positions', 50);
        if ((int) $config->max_open_positions > $maxPositions) {
            $failures[] = "Max open positions {$config->max_open_positions} exceeds global cap {$maxPositions}";
        }

        return $failures;
    }

    /** Portfolio drawdown vs equity peak. Updates the peak when equity grows. */
    protected function drawdownCheck(int $userId, ScannerConfig $config): array
    {
        $equity = $this->userEquity($userId);
        $peak = (float) $config->peak_equity;

        if ($peak > 0 && $equity > $peak) {
            $config->peak_equity = $equity;
            $config->save();
            $peak = $equity;
        }
        if ($equity <= 0 || $peak <= 0) {
            return [];
        }

        $dd = max(0.0, ($peak - $equity) / $peak * 100);
        $maxDd = (float) GlobalSetting::get('global_max_drawdown', 15.0);
        if ($dd >= $maxDd) {
            return [
                sprintf(
                    'Portfolio drawdown %.1f%% reached the %.0f%% limit — automated trading should be paused',
                    $dd, $maxDd
                ),
            ];
        }
        return [];
    }

    protected function openPositions(int $userId): \Illuminate\Support\Collection
    {
        return BotPosition::query()
            ->where('user_id', $userId)
            ->where('status', 'open')
            ->get();
    }

    protected function exposureChecks(ExchangeAccount $account, array $tradeContext, ScannerConfig $config, $open): array
    {
        $failures = [];

        $symbol = strtoupper($tradeContext['symbol'] ?? '');
        $base = strtoupper($tradeContext['base_asset'] ?? '');

        $slotsUsed = $open->count();
        $sameSymbol = $open->filter(fn ($p) => strtoupper($p->symbol) === $symbol)->count();
        $sameBase = $open->filter(fn ($p) => strtoupper(explode('-', $p->symbol)[0] ?? '') === $base)->count();

        $maxPositions = (int) $config->max_open_positions;
        $maxPerSymbol = $config->meta_max_per_symbol ?? 1;
        $maxPerBase = $config->meta_max_per_base ?? 1;

        if ($slotsUsed >= $maxPositions) {
            $failures[] = "Max open positions reached ({$maxPositions})";
        }
        if ($sameSymbol >= $maxPerSymbol) {
            $failures[] = "Position already open on {$symbol}";
        }
        if ($sameBase >= $maxPerBase) {
            $failures[] = "Base asset {$base} exposure limit reached";
        }

        return $failures;
    }

    /**
     * Correlated-cluster exposure: positions on admin-defined "major" assets
     * are the same broad risk, so combined notional is capped.
     */
    protected function correlationCheck(ExchangeAccount $account, array $tradeContext, ScannerConfig $config, $open): array
    {
        $cluster = $this->correlatedAssets();
        if (empty($cluster)) {
            return [];
        }

        $base = strtoupper($tradeContext['base_asset'] ?? '');
        $candidateInCluster = $base !== '' && in_array($base, $cluster, true);
        $clusterPositions = $open->filter(fn ($p) => in_array(
            strtoupper(explode('-', $p->symbol)[0] ?? ''),
            $cluster,
            true
        ));

        $clusterNotional = (float) $clusterPositions->sum(function ($p) {
            return (float) $p->quantity * (float) $p->entry_price;
        });

        if ($candidateInCluster) {
            $clusterNotional += $this->estimateNotional($account, $tradeContext, $config);
        }

        $maxClusterPct = (float) GlobalSetting::get('max_correlated_exposure', 30.0);
        $equity = $this->userEquity($account->user_id);
        $cap = $equity * ($maxClusterPct / 100);

        if ($cap > 0 && $clusterNotional > $cap) {
            return [
                sprintf(
                    'Correlated exposure $%s exceeds the $%s limit (%.0f%% of equity) on cluster %s',
                    number_format($clusterNotional, 0), number_format($cap, 0), $maxClusterPct,
                    implode(',', array_slice($cluster, 0, 3)).(count($cluster) > 3 ? '…' : '')
                ),
            ];
        }

        return [];
    }

    protected function estimateNotional(ExchangeAccount $account, array $tradeContext, ScannerConfig $config): float
    {
        $equity = $this->userEquity($account->user_id);
        $riskAmount = $equity * ((float) $config->risk_per_trade / 100);

        $entry = (float) ($tradeContext['entry_price'] ?? 0);
        $stop = (float) ($tradeContext['stop_loss'] ?? 0);
        if ($entry <= 0 || $stop <= 0) {
            return 0;
        }
        $stopDistPct = abs($entry - $stop) / $entry;

        if ($this->isFutures($tradeContext)) {
            $leverage = $this->plannedLeverage($account, $tradeContext, $config);
            return $stopDistPct > 0 ? ($riskAmount / $stopDistPct) * $leverage : 0;
        }

        return $stopDistPct > 0 ? $riskAmount / $stopDistPct : 0;
    }

    /**
     * Futures liquidation safety: stop must be inside the estimated isolated
     * liquidation price and the buffer must clear the admin minimum.
     */
    protected function liquidationCheck(ExchangeAccount $account, array $tradeContext, ScannerConfig $config): array
    {
        if (!$this->isFutures($tradeContext)) {
            return [];
        }

        $entry = (float) ($tradeContext['entry_price'] ?? 0);
        $stop = (float) ($tradeContext['stop_loss'] ?? 0);
        $long = in_array(strtolower($tradeContext['direction'] ?? 'long'), ['long', 'buy'], true);
        if ($entry <= 0 || $stop <= 0) {
            return ['Futures trade missing entry/stop for liquidation safety check'];
        }

        $leverage = $this->plannedLeverage($account, $tradeContext, $config);
        if ($leverage <= 1) {
            return [];
        }

        // Isolated-maintenance-margin approximation (0.5% maintenance margin).
        $liq = $long
            ? $entry * (1 - (1 / $leverage) + 0.005)
            : $entry * (1 + (1 / $leverage) - 0.005);

        $liqDistPct = abs($liq - $entry) / $entry * 100;
        $stopDistPct = abs($entry - $stop) / $entry * 100;
        $minDist = (float) GlobalSetting::get('liquidation_distance_min_pct', 3.0);

        if ($stopDistPct >= $liqDistPct) {
            return [
                sprintf(
                    'Stop loss (%.2f%% away) is beyond the estimated %dx liquidation price (%.2f%% away)',
                    $stopDistPct, $leverage, $liqDistPct
                ),
            ];
        }

        if ($liqDistPct < $minDist) {
            return [
                sprintf(
                    'Estimated liquidation distance %.2f%% is below the minimum %.1f%% at %dx leverage',
                    $liqDistPct, $minDist, $leverage
                ),
            ];
        }

        return [];
    }

    protected function plannedLeverage(ExchangeAccount $account, array $tradeContext, ScannerConfig $config): int
    {
        $limit = (int) ($tradeContext['leverage_limit'] ?? 0);
        $cap = (int) GlobalSetting::get('max_leverage', 20);
        $userMax = min((int) $config->max_leverage, $cap);
        if ($limit > 0) {
            return max(1, min((int) $limit, $userMax));
        }
        return max(1, $userMax);
    }

    protected function isFutures(array $tradeContext): bool
    {
        return strtolower($tradeContext['market_type'] ?? '') === 'futures';
    }

    protected function dailyLossCheck(int $userId, ScannerConfig $config): array
    {
        $today = now()->startOfDay();
        $trades = BotTrade::query()
            ->where('user_id', $userId)
            ->whereNotNull('closed_at')
            ->where('closed_at', '>=', $today)
            ->whereNotNull('pnl')
            ->get();

        $pnl = (float) $trades->sum('pnl');
        $maxLoss = (float) $config->max_daily_loss;

        $equity = $this->userEquity($userId);
        if ($equity > 0 && $pnl < -($equity * ($maxLoss / 100))) {
            return ["Daily loss limit reached (".round($pnl, 2)." <= -{$maxLoss}%)"];
        }
        return [];
    }

    protected function cooldownCheck(int $userId, array $tradeContext, ScannerConfig $config): array
    {
        $minutes = (int) $config->cooldown_minutes;
        if ($minutes <= 0) {
            return [];
        }

        $symbol = strtoupper($tradeContext['symbol'] ?? '');
        if ($symbol === '') {
            return [];
        }

        $last = BotTrade::query()
            ->where('user_id', $userId)
            ->where('symbol', $symbol)
            ->where('status', 'closed')
            ->whereNotNull('closed_at')
            ->orderByDesc('closed_at')
            ->first();

        if (!$last) {
            return [];
        }

        $remaining = (int) $last->closed_at->diffInMinutes(now());
        if ($remaining < $minutes) {
            $wait = $minutes - $remaining;
            return ["Cooldown active for {$symbol} ({$wait}m left)"];
        }
        return [];
    }

    protected function consecutiveLossCheck(int $userId, ScannerConfig $config): array
    {
        $maxLosses = (int) $config->max_consecutive_losses;
        if ($maxLosses <= 0) {
            return [];
        }

        $recent = BotTrade::query()
            ->where('user_id', $userId)
            ->where('status', 'closed')
            ->whereNotNull('closed_at')
            ->whereNotNull('pnl')
            ->orderByDesc('closed_at')
            ->limit((int) $maxLosses)
            ->get();

        if ($recent->count() < $maxLosses) {
            return [];
        }

        if ($recent->every(fn ($t) => (float) $t->pnl < 0)) {
            return ["Max {$maxLosses} consecutive losing trades reached — trading halted"];
        }
        return [];
    }

    protected function balanceCheck(ExchangeAccount $account): array
    {
        try {
            $balances = $this->balances($account);
        } catch (\Throwable $e) {
            Log::warning('scanner.risk.balance.fetch_failed', [
                'account' => $account->id, 'error' => $e->getMessage(),
            ]);
            return ['Could not fetch account balance'];
        }

        $usdt = static::usdtBalance($balances);
        if ($usdt <= 0) {
            if (static::totalBalance($balances) <= 0) {
                return ['No tradable balance available on account'];
            }
        }
        if ($usdt > 0 && $usdt < 5) {
            return ['Balance below minimum trading threshold'];
        }
        return [];
    }

    /**
     * Normalize adapter balance output (list of asset/total rows) to a USDT total.
     */
    public static function usdtBalance(array $balances): float
    {
        $sum = 0.0;
        foreach ($balances as $key => $value) {
            if (is_array($value)) {
                // list row: ['asset' => 'USDT', 'total' => ..]
                if (isset($value['asset']) && strtoupper($value['asset']) === 'USDT') {
                    $sum += (float) ($value['total'] ?? $value['free'] ?? 0);
                }
            } elseif (strtoupper((string) $key) === 'USDT') {
                $sum += (float) $value;
            }
        }
        return $sum;
    }

    public static function totalBalance(array $balances): float
    {
        $sum = 0.0;
        foreach ($balances as $key => $value) {
            if (is_array($value)) {
                $sum += (float) (array_key_exists('total', $value)
                    ? $value['total']
                    : ($value['free'] ?? 0));
            } else {
                $sum += (float) $value;
            }
        }
        return $sum;
    }

    protected function stalePriceCheck(array $tradeContext): array
    {
        $entry = (float) ($tradeContext['entry_price'] ?? 0);
        $current = (float) ($tradeContext['current_price'] ?? 0);
        if ($entry <= 0 || $current <= 0) return [];

        $deviation = abs($current - $entry) / $entry * 100;
        if ($deviation > ($tradeContext['max_price_slippage'] ?? 2.0)) {
            return ['Price moved more than allowed slippage from signal entry'];
        }
        return [];
    }

    protected function correlatedAssets(): array
    {
        $raw = GlobalSetting::get('correlated_assets', 'BTC,ETH,SOL,BNB,XRP,ADA,DOGE,AVAX,LINK,LTC,DOT,MATIC');
        if (is_array($raw)) {
            $list = $raw;
        } else {
            $list = array_map('trim', explode(',', (string) $raw));
        }
        return array_values(array_filter(array_unique(array_map('strtoupper', $list)), fn ($v) => $v !== ''));
    }

    protected function balances(ExchangeAccount $account): array
    {
        return Cache::remember("scanner.balances.{$account->id}", 30, function () use ($account) {
            $adapter = ExchangeFactory::make($account->exchange, $account->getDecryptedCredentials());
            return $adapter->getBalances();
        });
    }

    public function userEquity(int $userId): float
    {
        $cacheKey = "scanner.equity.{$userId}";
        if (Cache::has($cacheKey)) return (float) Cache::get($cacheKey);

        $equity = (float) Cache::remember($cacheKey, 300, function () use ($userId) {
            $sum = 0.0;
            foreach (ExchangeAccount::where('user_id', $userId)->where('status', 'connected')->get() as $account) {
                try {
                    $sum += static::totalBalance($this->balances($account));
                } catch (\Throwable $e) {
                    Log::debug('scanner.risk.balance.skip', [
                        'account' => $account->id, 'error' => $e->getMessage(),
                    ]);
                }
            }
            return $sum;
        });

        return $equity;
    }
}