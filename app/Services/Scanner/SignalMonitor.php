<?php

namespace App\Services\Scanner;

use App\Models\BotPosition;
use App\Models\BotTrade;
use App\Models\ExchangeAccount;
use App\Models\ScannerConfig;
use App\Models\ScannerSignal;
use App\Services\Exchanges\ExchangeFactory;
use Illuminate\Support\Facades\Log;

/**
 * Watches executed scanner signals and manages exits.
 * Supports TP, trailing stop, break-even, max-hold time, strategy invalidation,
 * and records realized PnL. Paper mode uses simulated fills.
 */
class SignalMonitor
{
    protected SignalRepository $repository;

    public function __construct(SignalRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return array{closed:int, processed:int}
     */
    public function monitor(bool $dryRun = false): array
    {
        $closed = 0;
        $processed = 0;

        $signals = ScannerSignal::where('status', 'executed')
            ->whereHas('exchangeAccount')
            ->get();

        foreach ($signals as $signal) {
            $position = BotPosition::where('signal_id', $signal->id)->where('status', 'open')->first();
            if (!$position) continue;
            ++$processed;

            $price = $this->currentPrice($signal);
            if ($price === null) continue;

            $config = ScannerConfig::find($signal->config_id);

            // Update trailing-watermark regardless of whether we close, so the high/low
            // watermark is always accurate for the next evaluate call.
            $this->updateTrailWatermark($position, $price);

            $decision = $this->evaluate($signal, $position, $price, $config);
            if ($decision === null) continue;

            if ($decision === 'trailing_stop' || $decision === 'time_exit' || $decision === 'invalidation') {
                $price = $this->resolveExitPrice($signal, $position, $decision, $price);
            }

            if (!$dryRun) {
                $this->close($signal, $position, $price, $decision, $config);
                ++$closed;
            }
        }

        return ['closed' => $closed, 'processed' => $processed];
    }

    protected function currentPrice(ScannerSignal $signal): ?float
    {
        try {
            $account = ExchangeAccount::find($signal->exchange_account_id);
            if (!$account) return null;
            $adapter = ExchangeFactory::make($account->exchange, $account->getDecryptedCredentials());
            $ticker = $adapter->getTicker($signal->symbol);
            return (float) ($ticker['price'] ?? null);
        } catch (\Throwable $e) {
            Log::debug('scanner.monitor.ticker_failed', [
                'signal' => $signal->id, 'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    protected function evaluate(ScannerSignal $signal, BotPosition $position, float $price, ?ScannerConfig $config): ?string
    {
        if (!$config) {
            return $this->simpleEvaluate($position, $price);
        }

        $long = $position->side === 'buy';
        $tp = (float) $position->take_profit;
        $sl = (float) $position->stop_loss;
        $entry = (float) $position->entry_price;

        // Max hold time.
        $maxHoldHours = $config->max_hold_hours;
        if ($maxHoldHours > 0 && $position->opened_at && $position->opened_at->diffInHours(now()) >= $maxHoldHours) {
            return 'time_exit';
        }

        // Break-even stop: move SL to entry once the price has moved favorably enough.
        if ($config->break_even_pct !== null && $entry > 0) {
            $movePct = $long
                ? ($price - $entry) / $entry * 100
                : ($entry - $price) / $entry * 100;
            if ($movePct >= (float) $config->break_even_pct && $sl < $entry && $long) {
                $this->moveStop($position, $entry);
            } elseif ($movePct >= (float) $config->break_even_pct && $sl > $entry && !$long) {
                $this->moveStop($position, $entry);
            }
        }

        // Re-read SL after possible break-even move.
        $sl = (float) $position->stop_loss;

        // Standard SL/TP.
        if ($long) {
            if ($tp > 0 && $price >= $tp) return 'tp_hit';
            if ($sl > 0 && $price <= $sl) return 'sl_hit';
        } else {
            if ($tp > 0 && $price <= $tp) return 'tp_hit';
            if ($sl > 0 && $price >= $sl) return 'sl_hit';
        }

        // Trailing stop: only when price is in profit and activation threshold reached.
        if ($config->trailing_enabled && $entry > 0) {
            $activation = (float) $config->trailing_activation_pct;
            $distPct = (float) $config->trailing_distance_pct;

            $high = (float) $position->trail_high;
            $low = (float) $position->trail_low;

            if ($long && $high > 0) {
                $profitPct = ($high - $entry) / $entry * 100;
                if ($profitPct >= $activation && $distPct > 0) {
                    $trailStop = $high * (1 - $distPct / 100);
                    if ($price <= $trailStop) return 'trailing_stop';
                }
            } elseif (!$long && $low > 0) {
                $profitPct = ($entry - $low) / $entry * 100;
                if ($profitPct >= $activation && $distPct > 0) {
                    $trailStop = $low * (1 + $distPct / 100);
                    if ($price >= $trailStop) return 'trailing_stop';
                }
            }
        }

        return null;
    }

    protected function simpleEvaluate(BotPosition $position, float $price): ?string
    {
        $long = $position->side === 'buy';
        $tp = (float) $position->take_profit;
        $sl = (float) $position->stop_loss;

        if ($long) {
            if ($tp > 0 && $price >= $tp) return 'tp_hit';
            if ($sl > 0 && $price <= $sl) return 'sl_hit';
            return null;
        }

        if ($tp > 0 && $price <= $tp) return 'tp_hit';
        if ($sl > 0 && $price >= $sl) return 'sl_hit';
        return null;
    }

    protected function updateTrailWatermark(BotPosition $position, float $price): void
    {
        $long = $position->side === 'buy';
        $changed = false;

        if ($long) {
            $currentHigh = (float) $position->trail_high;
            if ($price > $currentHigh) {
                $position->trail_high = $price;
                $changed = true;
            }
        } else {
            $currentLow = (float) $position->trail_low;
            if ($currentLow === 0.0 || $price < $currentLow) {
                $position->trail_low = $price;
                $changed = true;
            }
        }

        if ($changed) {
            $position->updateQuietly(['current_price' => $price]);
            $position->saveQuietly();
        }
    }

    protected function moveStop(BotPosition $position, float $newStop): void
    {
        $position->stop_loss = $newStop;
        $position->saveQuietly();
    }

    protected function resolveExitPrice(ScannerSignal $signal, BotPosition $position, string $decision, float $currentPrice): float
    {
        $long = $position->side === 'buy';
        if ($decision === 'trailing_stop') {
            $distPct = (float) (ScannerConfig::find($signal->config_id)?->trailing_distance_pct ?? 0.4);
            return $long
                ? (float) $position->trail_high * (1 - $distPct / 100)
                : (float) $position->trail_low * (1 + $distPct / 100);
        }
        // time_exit and invalidation: use current market price.
        return $currentPrice;
    }

    protected function close(ScannerSignal $signal, BotPosition $position, float $exitPrice, string $decision, ?ScannerConfig $config): bool
    {
        if ($config && !$config->paper_mode) {
            $this->reduceOnExchange($signal, $position);
        }

        $quantity = (float) $position->quantity;
        $entry = (float) $position->entry_price;
        $long = $position->side === 'buy';
        $pnl = $long
            ? $quantity * ($exitPrice - $entry)
            : $quantity * ($entry - $exitPrice);
        $pnlPct = $entry > 0 ? ($pnl / ($entry * $quantity)) * 100 : 0;

        $this->repository->recordOutcome($signal, $decision, $exitPrice, $pnl);

        // Estimate exit fees / slippage for paper fills (live fees are not always returned).
        $exitNotional = abs($exitPrice * $quantity);
        $fees = $exitNotional * 0.001; // 10 bps taker fee estimate.
        $slippage = abs($exitPrice - $entry) * $quantity; // proportional.

        BotTrade::where('signal_id', $signal->id)
            ->whereNull('closed_at')
            ->update([
                'exit_price' => $exitPrice,
                'exit_reason' => $decision,
                'pnl' => $pnl,
                'pnl_percent' => round($pnlPct, 4),
                'fees' => round($fees, 8),
                'slippage' => round($slippage, 8),
                'status' => 'closed',
                'closed_at' => now(),
            ]);

        $position->update([
            'status' => 'closed',
            'current_price' => $exitPrice,
            'unrealized_pnl' => $pnl,
        ]);

        ActivityLogger::log(
            $signal->user_id, 'signal.closed',
            "Closed {$signal->symbol} (".$signal->direction.") via {$decision}: ".
            (($pnl >= 0 ? '+' : '').round($pnl, 8).' ('.round($pnlPct, 2).'%)').'.',
            $pnl >= 0 ? 'info' : 'danger',
            $signal->config_id, $signal->id,
            ['exit_reason' => $decision, 'close_price' => $exitPrice, 'pnl' => $pnl, 'pnl_pct' => round($pnlPct, 4)]
        );

        return true;
    }

    protected function reduceOnExchange(ScannerSignal $signal, BotPosition $position): void
    {
        try {
            $account = ExchangeAccount::find($signal->exchange_account_id);
            if (!$account) return;
            $adapter = ExchangeFactory::make($account->exchange, $account->getDecryptedCredentials());
            $adapter->placeOrder([
                'symbol' => $signal->symbol,
                'type' => 'market',
                'side' => $position->side === 'buy' ? 'sell' : 'buy',
                'quantity' => (string) $position->quantity,
                'reduceOnly' => true,
            ]);
        } catch (\Throwable $e) {
            Log::error('scanner.monitor.close_failed', [
                'signal' => $signal->id, 'error' => $e->getMessage(),
            ]);
        }
    }
}