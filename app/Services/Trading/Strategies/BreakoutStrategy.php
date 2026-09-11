<?php

namespace App\Services\Trading\Strategies;

use App\Services\Trading\StrategyInterface;

class BreakoutStrategy implements StrategyInterface
{
    public function getName(): string { return 'breakout'; }

    public function generateSignal(array $candles, array $indicators, string $marketRegime): ?array
    {
        if ($marketRegime !== 'ranging' && $marketRegime !== 'high_volatility') return null;

        $highs = array_column($candles, 'high');
        $lows = array_column($candles, 'low');
        $closes = array_column($candles, 'close');
        $volumes = array_column($candles, 'volume');

        $recentHighs = array_slice($highs, -20);
        $recentLows = array_slice($lows, -20);
        $resistance = max($recentHighs);
        $support = min($recentLows);
        $lastClose = end($closes);
        $lastVolume = end($volumes);
        $avgVolume = array_sum(array_slice($volumes, -20)) / 20;

        $direction = null;
        $score = 0;

        if ($lastClose > $resistance && $lastVolume > $avgVolume * 1.5) {
            $direction = 'long';
            $score = 35;
            if ($lastVolume > $avgVolume * 2) $score += 15;
        } elseif ($lastClose < $support && $lastVolume > $avgVolume * 1.5) {
            $direction = 'short';
            $score = 35;
            if ($lastVolume > $avgVolume * 2) $score += 15;
        }

        if ($direction === null) return null;

        $atr = end($indicators['atr'] ?? [0]);
        $stopLoss = $direction === 'long' ? $lastClose - ($atr * 1.5) : $lastClose + ($atr * 1.5);
        $takeProfit = $direction === 'long' ? $lastClose + ($atr * 3) : $lastClose - ($atr * 3);

        return [
            'direction' => $direction,
            'entry_price' => $lastClose,
            'stop_loss' => $stopLoss,
            'take_profit' => $takeProfit,
            'score' => $score,
            'confidence' => min($score + 30, 100),
            'risk_reward' => abs($takeProfit - $lastClose) / abs($lastClose - $stopLoss),
            'strategy' => 'breakout',
        ];
    }
}
