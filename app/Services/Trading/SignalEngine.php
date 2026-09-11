<?php

namespace App\Services\Trading;

use App\Services\Analysis\TechnicalAnalysis;
use App\Services\Trading\Strategies\TrendFollowingStrategy;
use App\Services\Trading\Strategies\MomentumStrategy;
use App\Services\Trading\Strategies\BreakoutStrategy;
use App\Services\Trading\Strategies\MeanReversionStrategy;

class SignalEngine
{
    protected array $strategies = [];

    public function __construct()
    {
        $this->strategies[] = new TrendFollowingStrategy();
        $this->strategies[] = new MomentumStrategy();
        $this->strategies[] = new BreakoutStrategy();
        $this->strategies[] = new MeanReversionStrategy();
    }

    public function generateSignal(array $candles, string $strategyName): ?array
    {
        $closes = array_column($candles, 'close');
        $highs = array_column($candles, 'high');
        $lows = array_column($candles, 'low');
        $volumes = array_column($candles, 'volume');

        $indicators = [
            'ema_9' => TechnicalAnalysis::ema($closes, 9),
            'ema_21' => TechnicalAnalysis::ema($closes, 21),
            'ema_50' => TechnicalAnalysis::ema($closes, 50),
            'ema_100' => TechnicalAnalysis::ema($closes, 100),
            'ema_200' => TechnicalAnalysis::ema($closes, 200),
            'sma_20' => TechnicalAnalysis::sma($closes, 20),
            'sma_50' => TechnicalAnalysis::sma($closes, 50),
            'sma_200' => TechnicalAnalysis::sma($closes, 200),
            'rsi' => TechnicalAnalysis::rsi($closes),
            'macd' => TechnicalAnalysis::macd($closes),
            'atr' => TechnicalAnalysis::atr($highs, $lows, $closes),
            'bollinger_bands' => TechnicalAnalysis::bollingerBands($closes),
            'obv' => TechnicalAnalysis::obv($closes, $volumes),
            'vwap' => TechnicalAnalysis::vwap($highs, $lows, $closes, $volumes),
            'market_structure' => TechnicalAnalysis::detectMarketStructure($highs, $lows),
        ];

        $marketRegime = TechnicalAnalysis::detectMarketRegime($closes, $volumes);

        $strategy = $this->findStrategy($strategyName);
        if (!$strategy) return null;

        $signal = $strategy->generateSignal($candles, $indicators, $marketRegime);
        if (!$signal) return null;

        $signal['market_regime'] = $marketRegime;
        $signal['indicators'] = [
            'rsi' => end($indicators['rsi']),
            'ema_9' => end($indicators['ema_9']),
            'ema_21' => end($indicators['ema_21']),
            'macd_histogram' => end($indicators['macd']['histogram']),
            'atr' => end($indicators['atr']),
            'market_structure' => $indicators['market_structure'],
        ];

        return $signal;
    }

    protected function findStrategy(string $name): ?StrategyInterface
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->getName() === $name) return $strategy;
        }
        return null;
    }
}
