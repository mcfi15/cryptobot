<?php

namespace App\Services\Scanner;

use App\Models\ExchangeAccount;
use App\Services\Analysis\TechnicalAnalysis;
use App\Services\Exchanges\ExchangeFactory;
use Illuminate\Support\Facades\Log;

class TechnicalAnalyzerService
{
    /**
     * Fetch candles for one timeframe and derive a directional bias.
     *
     * @return array|null {candles, indicators, bias, close, atr_pct, volume_ratio}
     */
    public function analyzeTimeframe(ExchangeAccount $account, string $symbol, string $timeframe, int $limit = 240): ?array
    {
        try {
            $adapter = ExchangeFactory::make($account->exchange, $account->getDecryptedCredentials());
            $candles = $adapter->getKlines($symbol, IntervalMapper::canonical($account->exchange, $timeframe), $limit);
        } catch (\Throwable $e) {
            Log::debug('scanner.klines.failed', [
                'exchange' => $account->exchange, 'symbol' => $symbol,
                'timeframe' => $timeframe, 'error' => $e->getMessage(),
            ]);
            return null;
        }

        if (count($candles) < 60) return null;

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
            'bollinger' => TechnicalAnalysis::bollingerBands($closes),
            'stoch_rsi' => TechnicalAnalysis::stochasticRsi($closes),
            'obv' => TechnicalAnalysis::obv($closes, $volumes),
            'vwap' => TechnicalAnalysis::vwap($highs, $lows, $closes, $volumes),
        ];

        $bias = $this->timeframeBias($indicators);

        $close = (float) end($closes);
        $atr = (float) end($indicators['atr']);
        $atrPct = $close > 0 ? ($atr / $close) * 100 : 0;

        $avgVolume = array_sum(array_slice($volumes, -20)) / 20;
        $lastVolume = (float) end($volumes);

        $struct = TechnicalAnalysis::detectMarketStructure($highs, $lows);
        $breakout = $this->detectBreakout($close, $highs, $lows);

        return [
            'candles' => $candles,
            'timeframe' => $timeframe,
            'close' => $close,
            'bias' => $bias,
            'atr' => $atr,
            'atr_pct' => $atrPct,
            'volume_ratio' => $avgVolume > 0 ? round($lastVolume / $avgVolume, 2) : 1,
            'rsi' => end($indicators['rsi']),
            'macd_hist' => end($indicators['macd']['histogram']),
            'stoch_k' => end($indicators['stoch_rsi']['k']),
            'stoch_d' => end($indicators['stoch_rsi']['d']),
            'ema9' => end($indicators['ema_9']),
            'ema21' => end($indicators['ema_21']),
            'ema50' => end($indicators['ema_50']),
            'vwap' => end($indicators['vwap']),
            'bb_upper' => end($indicators['bollinger']['upper']),
            'bb_lower' => end($indicators['bollinger']['lower']),
            'obv_slope' => $this->obvSlope($indicators['obv']),
            'structure' => $struct,
            'breakout' => $breakout,
            'regime' => TechnicalAnalysis::detectMarketRegime($closes, $volumes),
            'pivot_high' => max(array_slice($highs, -40, 39)),
            'pivot_low' => min(array_slice($lows, -40, 39)),
        ];
    }

    /**
     * Multi-timeframe analysis snapshot for a full market.
     *
     * @param array $timeframes canonical timeframes (e.g. [4h,1h,15m])
     */
    public function analyzeMarket(ExchangeAccount $account, string $symbol, array $timeframes): array
    {
        $tfResult = [];
        foreach ($timeframes as $tf) {
            $analysis = $this->analyzeTimeframe($account, $symbol, $tf);
            if ($analysis === null) continue;
            $tfResult[$tf] = $analysis;
        }

        $primary = $timeframes[0] ?? '4h';
        $primaryAnalysis = $tfResult[$primary] ?? null;

        return [
            'exchange' => $account->exchange,
            'symbol' => $symbol,
            'timeframes' => $tfResult,
            'primary' => $primary,
            'primary_analysis' => $primaryAnalysis,
            'bias' => $this->aggregateBias($tfResult),
            'structure' => $primaryAnalysis['structure'] ?? ['trend' => 'unknown'],
            'breakout' => $primaryAnalysis['breakout'] ?? 'none',
            'regime' => $primaryAnalysis['regime'] ?? 'unknown',
            'volatility_class' => $this->classifyVolatility($primaryAnalysis['atr_pct'] ?? 0),
            'atr_pct' => $primaryAnalysis['atr_pct'] ?? 0,
            'atr' => $primaryAnalysis['atr'] ?? 0,
            'close' => $primaryAnalysis['close'] ?? 0,
            'volume_ratio' => $primaryAnalysis['volume_ratio'] ?? 1,
            'pivot_high' => $primaryAnalysis['pivot_high'] ?? 0,
            'pivot_low' => $primaryAnalysis['pivot_low'] ?? 0,
            'timeframes_count' => count($tfResult),
        ];
    }

    protected function timeframeBias(array $i): string
    {
        $ema9 = end($i['ema_9']);
        $ema21 = end($i['ema_21']);
        $ema50 = end($i['ema_50']);
        $macdHist = end($i['macd']['histogram']);
        $rsi = end($i['rsi']);

        $bullish = 0;
        $bearish = 0;

        if ($ema9 != null && $ema21 != null) {
            if ($ema9 > $ema21) $bullish++; else $bearish++;
        }
        if ($ema21 != null && $ema50 != null) {
            if ($ema21 > $ema50) $bullish++; else $bearish++;
        }
        if ($macdHist != null) {
            if ($macdHist > 0) $bullish++; else $bearish++;
        }
        if ($rsi != null) {
            if ($rsi > 52) $bullish++;
            elseif ($rsi < 48) $bearish++;
        }

        if ($bullish >= 3 && $bearish === 0) return 'long';
        if ($bearish >= 3 && $bullish === 0) return 'short';
        if ($bullish > $bearish) return 'long';
        if ($bearish > $bullish) return 'short';
        return 'neutral';
    }

    protected function aggregateBias(array $tfResult): array
    {
        if (empty($tfResult)) return ['direction' => 'neutral', 'agreement' => 0, 'count' => 0];

        $long = 0;
        $short = 0;
        foreach ($tfResult as $tf) {
            if ($tf['bias'] === 'long') $long++;
            if ($tf['bias'] === 'short') $short++;
        }
        $total = count($tfResult);
        $direction = $long >= $short ? ($long === $short ? 'neutral' : 'long') : 'short';

        return [
            'direction' => $direction,
            'agreement' => round(max($long, $short) / $total * 100),
            'long_tfs' => $long,
            'short_tfs' => $short,
            'count' => $total,
        ];
    }

    protected function detectBreakout(float $close, array $highs, array $lows): string
    {
        if (count($highs) < 25) return 'none';

        $recentHigh = max(array_slice($highs, -21, 20));
        $recentLow = min(array_slice($lows, -21, 20));

        if ($close > $recentHigh) return 'breakout';
        if ($close < $recentLow) return 'breakdown';
        if ($close > $recentHigh * 0.995) return 'near_breakout';
        if ($close < $recentLow * 1.005) return 'near_breakdown';
        return 'none';
    }

    public function classifyVolatility(float $atrPct): string
    {
        if ($atrPct < 1.0) return 'low';
        if ($atrPct < 2.5) return 'normal';
        if ($atrPct < 5.0) return 'high';
        return 'extreme';
    }

    public function volatilityRank(string $class): int
    {
        return match ($class) {
            'low' => 1, 'normal' => 2, 'high' => 3, 'extreme' => 4, default => 0,
        };
    }

    protected function obvSlope(array $obv): float
    {
        if (count($obv) < 11) return 0;
        $slice = array_slice($obv, -11);
        $first = $slice[0];
        $last = end($slice);
        return $last - $first;
    }
}