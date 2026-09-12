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
 * Watches executed scanner signals and closes them on TP/SL.
 * Paper mode uses simulated fills; live mode additionally places reduce-only closes.
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
    public function monitor(): array
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

            $decision = $this->evaluate($signal, $position, $price);
            if ($decision === null) continue;

            if ($lander = $this->close($signal, $position, $price, $decision)) {
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

    protected function evaluate(ScannerSignal $signal, BotPosition $position, float $price): ?string
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

    protected function close(ScannerSignal $signal, BotPosition $position, float $exitPrice, string $decision): bool
    {
        $config = ScannerConfig::find($signal->config_id);
        $long = $position->side === 'buy';

        if ($config && !$config->paper_mode) {
            $this->reduceOnExchange($signal, $position);
        }

        $quantity = (float) $position->quantity;
        $entry = (float) $position->entry_price;
        $pnl = $long
            ? $quantity * ($exitPrice - $entry)
            : $quantity * ($entry - $exitPrice);
        $pnlPct = $entry > 0 ? ($pnl / ($entry * $quantity)) * 100 : 0;

        $this->repository->recordOutcome($signal, $decision, $exitPrice, $pnl);

        BotTrade::where('signal_id', $signal->id)
            ->whereNull('closed_at')
            ->update([
                'exit_price' => $exitPrice,
                'pnl' => $pnl,
                'pnl_percent' => round($pnlPct, 4),
                'status' => 'closed',
                'closed_at' => now(),
            ]);

        $position->update([
            'status' => 'closed',
            'current_price' => $exitPrice,
            'unrealized_pnl' => $pnl,
        ]);

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