<?php

namespace App\Services\Scanner;

use App\Models\ScannerConfig;
use App\Models\ScannerSignal;
use Illuminate\Support\Carbon;

class SignalRepository
{
    /**
     * Stable fingerprint for deduplication: exchange|symbol|direction|timeframe.
     */
    public function fingerprint(string $exchange, string $symbol, string $direction, string $timeframe): string
    {
        return md5(sprintf('%s|%s|%s|%s', $exchange, $symbol, strtolower($direction), strtolower($timeframe)));
    }

    public function existingActive(string $fingerprint): ?ScannerSignal
    {
        return ScannerSignal::query()
            ->where('fingerprint', $fingerprint)
            ->whereIn('status', self::activeStatuses())
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();
    }

    public function isDuplicate(string $fingerprint): bool
    {
        return $this->existingActive($fingerprint) !== null;
    }

    public static function activeStatuses(): array
    {
        return ['detected', 'qualified', 'watching', 'entry_pending', 'entry_confirmed'];
    }

    /**
     * Persist a scanner signal with full analysis snapshot.
     */
    public function create(
        int $userId,
        int $configId,
        array $account,
        string $exchange,
        string $symbol,
        string $baseAsset,
        string $quoteAsset,
        string $marketType,
        string $direction,
        string $timeframe,
        string $strategy,
        float $entryPrice,
        float $stopLoss,
        float $takeProfit,
        float $riskReward,
        int $score,
        string $scoreQuality,
        array $analysis,
        ScannerConfig $config,
        string $status = 'qualified',
        string $source = 'auto'
    ): ScannerSignal {
        $signal = new ScannerSignal();
        $signal->user_id = $userId;
        $signal->config_id = $configId;
        $signal->exchange_account_id = $account['id'];
        $signal->exchange = $exchange;
        $signal->symbol = $symbol;
        $signal->base_asset = $baseAsset;
        $signal->quote_asset = $quoteAsset;
        $signal->market_type = $marketType;
        $signal->direction = $direction;
        $signal->timeframe = $timeframe;
        $signal->strategy = $strategy;
        $signal->status = $status;
        $signal->signal_score = $score;
        $signal->score_quality = $scoreQuality;
        $signal->ai_probability = $analysis['ai']['probability'] ?? null;
        $signal->entry_price = $entryPrice;
        $signal->stop_loss = $stopLoss;
        $signal->take_profit = $takeProfit;
        $signal->risk_reward = round($riskReward, 2);
        $signal->current_price = $analysis['close'] ?? $entryPrice;
        $signal->volume_24h = $analysis['volume_24h'] ?? null;
        $signal->spread_pct = $analysis['spread_pct'] ?? null;
        $signal->volatility_class = $analysis['volatility_class'] ?? null;
        $signal->market_regime = $analysis['regime'] ?? null;
        $signal->score_breakdown = $analysis['breakdown'] ?? null;
        $signal->indicators = $analysis['indicators'] ?? null;
        $signal->structure = $analysis['structure'] ?? null;
        $signal->timeframe_analysis = $analysis['timeframes'] ?? null;
        $signal->ai_analysis = $analysis['ai'] ?? null;
        $signal->reasons = $analysis['ai']['reasons'] ?? null;
        $signal->meta = $analysis['meta'] ?? ['source' => $source];
        $signal->fingerprint = $this->fingerprint($exchange, $symbol, $direction, $timeframe);
        $signal->expires_at = now()->addMinutes((int) $config->signal_expiry_minutes);
        $signal->save();

        return $signal;
    }

    public function createRejected(
        int $userId,
        int $configId,
        array $account,
        string $exchange,
        string $symbol,
        string $baseAsset,
        string $quoteAsset,
        string $marketType,
        string $direction,
        string $timeframe,
        string $strategy,
        float $entryPrice,
        int $score,
        string $reason,
        array $analysis
    ): ScannerSignal {
        $config = ScannerConfig::find($configId);
        $stop = $entryPrice ?: 1;
        $signal = new ScannerSignal();
        $signal->user_id = $userId;
        $signal->config_id = $configId;
        $signal->exchange_account_id = $account['id'];
        $signal->exchange = $exchange;
        $signal->symbol = $symbol;
        $signal->base_asset = $baseAsset;
        $signal->quote_asset = $quoteAsset;
        $signal->market_type = $marketType;
        $signal->direction = $direction;
        $signal->timeframe = $timeframe;
        $signal->strategy = $strategy;
        $signal->status = 'rejected';
        $signal->signal_score = $score;
        $signal->score_quality = 'reject';
        $signal->entry_price = $entryPrice;
        $signal->stop_loss = $stop;
        $signal->take_profit = $stop;
        $signal->risk_reward = 0;
        $signal->current_price = $analysis['close'] ?? $entryPrice;
        $signal->volatility_class = $analysis['volatility_class'] ?? null;
        $signal->score_breakdown = $analysis['breakdown'] ?? null;
        $signal->invalidation = ['reason' => $reason, 'rejected_at' => now()->toDateTimeString()];
        $signal->meta = ['source' => 'auto', 'rejected' => true];
        $signal->fingerprint = $this->fingerprint($exchange, $symbol, $direction, $timeframe);
        $signal->expires_at = now()->addHours(1);
        $signal->save();

        return $signal;
    }

    public function expireDue(): int
    {
        return ScannerSignal::query()
            ->whereIn('status', self::activeStatuses())
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired', 'outcome' => 'expired']);
    }

    public function markWatchlist(ScannerSignal $signal): void
    {
        $signal->watch = true;
        $signal->status = 'watching';
        $signal->save();
    }

    public function removeWatchlist(ScannerSignal $signal): void
    {
        $signal->watch = false;
        if (in_array($signal->status, ['watching', 'entry_pending'], true)) {
            $signal->status = 'qualified';
        }
        $signal->save();
    }

    /**
     * User dismissed the signal — it becomes canceled and is never executed.
     */
    public function markDismissed(ScannerSignal $signal, string $reason = 'Dismissed by user'): void
    {
        $signal->status = 'canceled';
        $signal->watch = false;
        $signal->invalidation = array_merge($signal->invalidation ?? [], [
            'reason' => $reason,
            'dismissed_at' => now()->toDateTimeString(),
        ]);
        $signal->save();
    }

    public function markExecuted(ScannerSignal $signal): void
    {
        $signal->status = 'executed';
        $signal->executed_at = now();
        $signal->save();
    }

    public function recordOutcome(ScannerSignal $signal, string $outcome, ?float $exitPrice, ?float $pnl): void
    {
        $signal->status = 'closed';
        $signal->outcome = $outcome;
        $signal->outcome_pnl = $pnl;
        $signal->closed_at = now();
        $signal->save();
    }
}