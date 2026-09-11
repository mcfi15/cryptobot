<?php

namespace App\Services\Trading\Strategies;

use App\Services\Trading\StrategyInterface;

class MeanReversionStrategy implements StrategyInterface
{
    public function getName(): string { return 'mean_reversion'; }

    public function generateSignal(array $candles, array $indicators, string $marketRegime): ?array
    {
        if ($marketRegime !== 'ranging') return null;

        $closes = array_column($candles, 'close');
        $bb = $indicators['bollinger_bands'] ?? null;
        $rsi = end($indicators['rsi'] ?? [null]);

        if ($bb === null || $rsi === null) return null;

        $lastClose = end($closes);
        $upperBB = end($bb['upper'] ?? [null]);
        $lowerBB = end($bb['lower'] ?? [null]);
        $middleBB = end($bb['middle'] ?? [null]);

        if ($upperBB === null || $lowerBB === null) return null;

        $direction = null;
        $score = 0;

        if ($lastClose < $lowerBB && $rsi < 35) {
            $direction = 'long';
            $score = 30;
            if ($rsi < 25) $score += 15;
        } elseif ($lastClose > $upperBB && $rsi > 65) {
            $direction = 'short';
            $score = 30;
            if ($rsi > 75) $score += 15;
        }

        if ($direction === null) return null;

        $stopLoss = $direction === 'long' ? $lowerBB * 0.99 : $upperBB * 1.01;
        $takeProfit = $middleBB;

        return [
            'direction' => $direction,
            'entry_price' => $lastClose,
            'stop_loss' => $stopLoss,
            'take_profit' => $takeProfit,
            'score' => $score,
            'confidence' => min($score + 40, 100),
            'risk_reward' => abs($takeProfit - $lastClose) / abs($lastClose - $stopLoss),
            'strategy' => 'mean_reversion',
        ];
    }
}
