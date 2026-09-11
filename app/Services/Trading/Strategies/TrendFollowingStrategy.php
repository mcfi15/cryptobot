<?php

namespace App\Services\Trading\Strategies;

use App\Services\Trading\StrategyInterface;

class TrendFollowingStrategy implements StrategyInterface
{
    public function getName(): string { return 'trend_following'; }

    public function generateSignal(array $candles, array $indicators, string $marketRegime): ?array
    {
        if ($marketRegime !== 'trending_up' && $marketRegime !== 'trending_down') return null;

        $closes = array_column($candles, 'close');
        $ema9 = end($indicators['ema_9'] ?? [null]);
        $ema21 = end($indicators['ema_21'] ?? [null]);
        $ema50 = end($indicators['ema_50'] ?? [null]);
        $rsi = end($indicators['rsi'] ?? [null]);
        $macd = $indicators['macd']['histogram'] ?? [null];
        $lastMacd = end($macd);

        if ($ema9 === null || $ema21 === null || $rsi === null || $lastMacd === null) return null;

        $direction = null;
        $score = 0;

        if ($marketRegime === 'trending_up' && $ema9 > $ema21 && $rsi > 50 && $rsi < 75 && $lastMacd > 0) {
            $direction = 'long';
            $score += 20;
            if ($ema21 > ($ema50 ?? 0)) $score += 15;
            if ($rsi > 55 && $rsi < 70) $score += 10;
            if ($lastMacd > 0) $score += 10;
        } elseif ($marketRegime === 'trending_down' && $ema9 < $ema21 && $rsi < 50 && $rsi > 25 && $lastMacd < 0) {
            $direction = 'short';
            $score += 20;
            if ($ema21 < ($ema50 ?? PHP_INT_MAX)) $score += 15;
            if ($rsi < 45 && $rsi > 30) $score += 10;
            if ($lastMacd < 0) $score += 10;
        }

        if ($direction === null) return null;

        $lastPrice = end($closes);
        $atr = end($indicators['atr'] ?? [0]);
        $stopLoss = $direction === 'long' ? $lastPrice - ($atr * 1.5) : $lastPrice + ($atr * 1.5);
        $takeProfit = $direction === 'long' ? $lastPrice + ($atr * 3) : $lastPrice - ($atr * 3);

        return [
            'direction' => $direction,
            'entry_price' => $lastPrice,
            'stop_loss' => $stopLoss,
            'take_profit' => $takeProfit,
            'score' => $score,
            'confidence' => min($score + 30, 100),
            'risk_reward' => abs($takeProfit - $lastPrice) / abs($lastPrice - $stopLoss),
            'strategy' => 'trend_following',
        ];
    }
}
