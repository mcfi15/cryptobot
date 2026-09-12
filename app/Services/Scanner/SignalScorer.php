<?php

namespace App\Services\Scanner;

use App\Models\ScannerConfig;

class SignalScorer
{
    protected AiEngine $ai;

    public function __construct(AiEngine $ai)
    {
        $this->ai = $ai;
    }

    /**
     * Weighted 0-100 signal score built from technical/AI sub-components.
     * Returns score + breakdown for transparency.
     */
    public function score(array $snapshot, float $quoteVolume, ScannerConfig $config): array
    {
        $weights = $this->weights($config);
        $direction = $snapshot['bias']['direction'] ?? 'neutral';

        if ($direction === 'neutral') {
            return ['score' => 0, 'direction' => 'neutral', 'breakdown' => [], 'ai' => null];
        }

        $ai = $this->ai->predict($snapshot, $direction);

        $components = [
            'trend' => round($this->trendScore($snapshot, $direction) * 100),
            'momentum' => round($this->momentumScore($snapshot['primary_analysis'], $direction) * 100),
            'volume' => round($this->volumeScore($snapshot['volume_ratio'] ?? 1, $quoteVolume) * 100),
            'structure' => round($this->structureScore($snapshot, $direction) * 100),
            'mtf' => round(($snapshot['bias']['agreement'] ?? 0) * ($snapshot['bias']['count'] >= 2 ? 1 : 0.6)),
            'ai' => round($ai['probability']),
        ];

        $score = 0.0;
        foreach ($weights as $key => $weight) {
            $score += ($components[$key] ?? 0) * $weight;
        }

        return [
            'score' => (int) round($score),
            'direction' => $direction,
            'breakdown' => [
                'components' => $components,
                'weights' => $weights,
            ],
            'ai' => [
                'probability' => $ai['probability'],
                'features' => $ai['features'],
                'reasons' => $ai['reasons'],
            ],
        ];
    }

    protected function weights(ScannerConfig $config): array
    {
        $custom = $config->score_weights ?? [];
        $merged = array_merge([
            'trend' => 20,
            'momentum' => 15,
            'volume' => 15,
            'structure' => 15,
            'mtf' => 15,
            'ai' => 20,
        ], is_array($custom) ? $custom : []);

        $total = array_sum($merged);
        if ($total <= 0) {
            return array_fill_keys(array_keys($merged), 1 / max(1, count($merged)));
        }

        return array_map(fn ($w) => $w / $total, $merged);
    }

    protected function trendScore(array $snapshot, string $direction): float
    {
        $a = $snapshot['primary_analysis'];
        if (!$a) return 0;

        $long = $direction === 'long';
        $e9 = (float) ($a['ema9'] ?? 0);
        $e21 = (float) ($a['ema21'] ?? 0);
        $e50 = (float) ($a['ema50'] ?? 0);
        $close = (float) ($a['close'] ?? 0);
        $higher = $snapshot['bias']['direction'] ?? 'neutral';

        $score = 0.0;
        if ($long) {
            if ($e9 > $e21) $score += 0.25;
            if ($e21 > $e50) $score += 0.25;
            if ($close > $e9) $score += 0.25;
            if ($higher === 'long') $score += 0.25;
        } else {
            if ($e9 < $e21) $score += 0.25;
            if ($e21 < $e50) $score += 0.25;
            if ($close < $e9) $score += 0.25;
            if ($higher === 'short') $score += 0.25;
        }
        return $score;
    }

    protected function momentumScore(?array $a, string $direction): float
    {
        if (!$a) return 0;
        $long = $direction === 'long';
        $rsi = (float) ($a['rsi'] ?? 50);
        $k = $long ? $rsi : 100 - $rsi;
        $hist = (float) ($a['macd_hist'] ?? 0);
        $m = $long ? $hist : -$hist;

        $score = 0.0;
        if ($k > 60 && $k < 72) $score += 0.4;
        elseif ($k >= 50 && $k < 60) $score += 0.3;
        elseif ($k > 72) $score += 0.1;
        else $score += 0.15;

        if ($m > 0) $score += 0.35;
        else $score += 0.1;

        if (($a['stoch_k'] ?? 50) > ($a['stoch_d'] ?? 50)) $score += 0.25;

        return min(1, round($score, 3));
    }

    protected function volumeScore(float $volumeRatio, float $quoteVolume): float
    {
        $score = 0.0;
        if ($volumeRatio >= 1.5) $score += 0.5;
        elseif ($volumeRatio >= 1.1) $score += 0.35;
        elseif ($volumeRatio >= 0.9) $score += 0.25;
        else $score += 0.1;

        // Absolute liquidity (observable 24h quote volume).
        if ($quoteVolume >= 1_000_000_000) $score += 0.5;
        elseif ($quoteVolume >= 100_000_000) $score += 0.4;
        elseif ($quoteVolume >= 10_000_000) $score += 0.3;
        elseif ($quoteVolume >= 1_000_000) $score += 0.2;
        else $score += 0.05;

        return min(1, round($score, 3));
    }

    protected function structureScore(array $snapshot, string $direction): float
    {
        $long = $direction === 'long';
        $trend = $snapshot['structure']['trend'] ?? 'unknown';
        $breakout = $snapshot['breakout'] ?? 'none';

        $score = 0.4;
        if (($long && $trend === 'bullish') || (!$long && $trend === 'bearish')) $score = 0.7;

        if (($long && $breakout === 'breakout') || (!$long && $breakout === 'breakdown')) return 1.0;
        if (($long && $breakout === 'near_breakout') || (!$long && $breakout === 'near_breakdown')) return 0.8;

        return $score;
    }
}