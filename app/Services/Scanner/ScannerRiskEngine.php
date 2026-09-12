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
 */
class ScannerRiskEngine
{
    public function evaluate(ExchangeAccount $account, array $tradeContext, ScannerConfig $config, bool $manual = false): array
    {
        $failures = [];

        if (GlobalSetting::get('global_trading_kill_switch', false)) {
            $failures[] = 'Global trading kill switch is ON';
        }
        if (GlobalSetting::get('scanner_kill_switch', false)) {
            $failures[] = 'Scanner kill switch is ON';
        }
        if (!$manual && $config->status !== 'running') {
            $failures[] = 'Scanner is stopped';
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

        $failures = array_merge($failures, $this->exposureChecks($account, $tradeContext, $config));
        $failures = array_merge($failures, $this->dailyLossCheck($account->user_id, $config));
        $failures = array_merge($failures, $this->balanceCheck($account));
        $failures = array_merge($failures, $this->stalePriceCheck($tradeContext));

        return [
            'pass' => empty($failures),
            'failures' => $failures,
        ];
    }

    protected function exposureChecks(ExchangeAccount $account, array $tradeContext, ScannerConfig $config): array
    {
        $failures = [];

        $open = BotPosition::query()
            ->where('user_id', $account->user_id)
            ->where('status', 'open')
            ->get();

        $symbol = strtoupper($tradeContext['symbol']);
        $base = strtoupper($tradeContext['base_asset']);

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

    protected function balances(ExchangeAccount $account): array
    {
        return Cache::remember("scanner.balances.{$account->id}", 30, function () use ($account) {
            $adapter = ExchangeFactory::make($account->exchange, $account->getDecryptedCredentials());
            return $adapter->getBalances();
        });
    }

    protected function userEquity(int $userId): float
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