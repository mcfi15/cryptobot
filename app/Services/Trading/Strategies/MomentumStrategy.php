<?php

namespace App\Services\Trading\Strategies;

use App\Services\Trading\StrategyInterface;

class MomentumStrategy implements StrategyInterface
{
    public function getName(): string { return 'momentum'; }

    public function generateSignal(array $candles, array $indicators, string $marketRegime): ?array
    {
        $closes = array_column($candles, 'close');
        $rsi = end($indicators['rsi'] ?? [null]);
        $macd = $indicators['macd'] ?? [];
        $lastMacdHist = end($macd['histogram'] ?? [null]);
        $prevMacdHist = count($macd['histogram'] ?? []) > 1
            ? $macd['histogram'][count($macd['histogram']) - 2] : null;

        if ($rsi === null || $lastMacdHist === null || $prevMacdHist === null) return null;

        $direction = null;
        $score = 0;

        if ($rsi < 40 && $lastMacdHist > $prevMacdHist && $lastMacdHist > 0) {
            $direction = 'long';
            $score = 30;
            if ($rsi < 30) $score += 15;
            if ($lastMacdHist > 0 && $prevMacdHist < 0) $score += 20;
        } elseif ($rsi > 60 && $lastMacdHist < $prevMacdHist && $lastMacdHist < 0) {
            $direction = 'short';
            $score = 30;
            if ($rsi > 70) $score += 15;
            if ($lastMacdHist < 0 && $prevMacdHist > 0) $score += 20;
        }

        if ($direction === null) return null;

        $lastPrice = end($closes);
        $atr = end($indicators['atr'] ?? [0]);
        $stopLoss = $direction === 'long' ? $lastPrice - ($atr * 2) : $lastPrice + ($atr * 2);
        $takeProfit = $direction === 'long' ? $lastPrice + ($atr * 2.5) : $lastPrice - ($atr * 2.5);

        return [
            'direction' => $direction,
            'entry_price' => $lastPrice,
            'stop_loss' => $stopLoss,
            'take_profit' => $takeProfit,
            'score' => $score,
            'confidence' => min($score + 35, 100),
            'risk_reward' => abs($takeProfit - $lastPrice) / abs($lastPrice - $stopLoss),
            'strategy' => 'momentum',
        ];
    }
}
