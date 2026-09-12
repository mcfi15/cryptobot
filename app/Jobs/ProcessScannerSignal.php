<?php

namespace App\Jobs;

use App\Models\ExchangeMarket;
use App\Models\ScannerConfig;
use App\Models\ScannerSignal;
use App\Services\Scanner\ScannerExecutionService;
use App\Services\Scanner\ScannerRiskEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessScannerSignal implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct(public int $scannerSignalId)
    {
    }

    public function uniqueId(): string
    {
        return 'scanner-signal-'.$this->scannerSignalId;
    }

    public function handle(ScannerRiskEngine $riskEngine, ScannerExecutionService $execution): void
    {
        $signal = ScannerSignal::find($this->scannerSignalId);
        if (!$signal || $signal->status !== 'qualified') {
            return;
        }

        $config = ScannerConfig::find($signal->config_id);
        $account = $signal->exchange_account_id ? $signal->exchangeAccount() : null;
        if (!$config || !$account) {
            return;
        }

        // Fresh risk re-check at execution time — final authority. Never bypassed.
        $risk = $riskEngine->evaluate($account, [
            'symbol' => $signal->symbol,
            'base_asset' => $signal->base_asset,
            'entry_price' => (float) $signal->entry_price,
            'current_price' => (float) $signal->current_price ?: (float) $signal->entry_price,
        ], $config);

        if (!$risk['pass']) {
            $signal->status = 'rejected';
            $signal->invalidation = array_merge($signal->invalidation ?? [], [
                'reason' => implode('; ', $risk['failures']),
                'stage' => 'auto_execution_risk_gate',
            ]);
            $signal->save();
            Log::info('scanner.auto.risk_blocked', [
                'signal' => $signal->id, 'failures' => $risk['failures'],
            ]);
            return;
        }

        $market = ExchangeMarket::where('exchange', $signal->exchange)
            ->where('symbol', $signal->symbol)
            ->where('market_type', $signal->market_type)
            ->first();

        if (!$market) {
            $signal->status = 'rejected';
            $signal->invalidation = array_merge($signal->invalidation ?? [], [
                'reason' => 'Market metadata missing at execution time',
                'stage' => 'auto_execution',
            ]);
            $signal->save();
            return;
        }

        $result = $execution->execute($signal, $account, $market, $config);
        if (!$result['ok']) {
            $signal->status = 'rejected';
            $signal->invalidation = array_merge($signal->invalidation ?? [], [
                'reason' => $result['message'],
                'stage' => 'auto_execution',
            ]);
            $signal->save();
        }
    }
}