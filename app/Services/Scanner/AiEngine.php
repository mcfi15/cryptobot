<?php

namespace App\Services\Scanner;

/**
 * Statistical probability engine.
 *
 * Produces an honest probability that a candidate trade reaches its target before its
 * stop, computed from the actual measured technical features. Weights are fixed,
 * transparent heuristics (never presented as a measured historical accuracy).
 */
class AiEngine
{
    protected array $weights = [
        'ema_align' => 0.30,
        'macd' => 0.18,
        'rsi_zone' => 0.16,
        'structure' => 0.14,
        'mtf' => 0.12,
        'volume' => 0.10,
    ];

    public function predict(array $snapshot, string $direction): array
    {
        $a = $snapshot['primary_analysis'];
        $bias = $snapshot['bias'] ?? ['direction' => 'neutral', 'agreement' => 0];
        $structure = $snapshot['structure'] ?? ['trend' => 'unknown'];

        $features = [];
        $features['ema_align'] = $this->emaAlignment($a, $direction);
        $features['macd'] = $this->macdFeature($a, $direction);
        $features['rsi_zone'] = $this->rsiZone($a, $direction);
        $features['structure'] = $this->structureFeature($structure, $snapshot['breakout'] ?? 'none', $direction);
        $features['mtf'] = $this->mtfFeature($bias, array_keys($snapshot['timeframes'] ?? []), $direction);
        $features['volume'] = $this->volumeFeature($a['volume_ratio'] ?? 1, $direction);

        $z = 0.0;
        foreach ($this->weights as $key => $weight) {
            $z += $weight * $features[$key];
        }

        $probability = round(100 / (1 + exp(-$z)), 2);

        return [
            'probability' => min(99.0, max($probability, 1.0)),
            'direction' => $direction,
            'features' => $features,
            'reasons' => $this->reasons($features),
        ];
    }

    protected function emaAlignment(?array $a, string $direction): float
    {
        if (!$a) return 0;
        $long = $direction === 'long' || $direction === 'buy';
        $e9 = (float) ($a['ema9'] ?? 0);
        $e21 = (float) ($a['ema21'] ?? 0);
        $e50 = (float) ($a['ema50'] ?? 0);
        $close = (float) ($a['close'] ?? 0);

        $score = 0.0;
        if ($long) {
            if ($e9 > $e21) $score += 1 / 3;
            if ($e21 > $e50) $score += 1 / 3;
            if ($close > $e9) $score += 1 / 3;
        } else {
            if ($e9 < $e21) $score += 1 / 3;
            if ($e21 < $e50) $score += 1 / 3;
            if ($close < $e9) $score += 1 / 3;
        }
        return round($score, 3);
    }

    protected function macdFeature(?array $a, string $direction): float
    {
        if (!$a) return 0;
        $long = $direction === 'long' || $direction === 'buy';
        $hist = (float) ($a['macd_hist'] ?? 0);
        $rsi = (float) ($a['rsi'] ?? 50);

        $m = $long ? $hist : -$hist;
        $v = $m > 0 ? 1.0 : ($m > -0 ? 0.5 : 0.2);

        // RSI overextension dampening.
        if ($long && $rsi > 75) $v *= 0.4;
        if (!$long && $rsi < 25) $v *= 0.4;

        return round($v, 3);
    }

    protected function rsiZone(?array $a, string $direction): float
    {
        if (!$a) return 0.5;
        $rsi = (float) ($a['rsi'] ?? 50);
        $stochK = (float) ($a['stoch_k'] ?? 50);
        $stochD = (float) ($a['stoch_d'] ?? 50);

        $long = $direction === 'long' || $direction === 'buy';
        $k = $long ? $rsi : 100 - $rsi;

        if ($k >= 60 && $k <= 72) $v = 1.0;
        elseif ($k >= 50 && $k < 60) $v = 0.75;
        elseif ($k >= 40 && $k < 50) $v = 0.5;
        elseif ($k > 72) $v = 0.25;
        else $v = 0.3;

        // Stochastic cross confirmation.
        if ($long && $stochK > $stochD) $v = min(1, $v + 0.1);
        if (!$long && $stochK < $stochD) $v = min(1, $v + 0.1);

        return round($v, 3);
    }

    protected function structureFeature(array $structure, string $breakout, string $direction): float
    {
        $long = $direction === 'long' || $direction === 'buy';

        $v = 0.5;
        $trend = $structure['trend'] ?? 'unknown';
        if (($long && $trend === 'bullish') || (!$long && $trend === 'bearish')) $v = 0.75;

        if (($long && $breakout === 'breakout') || (!$long && $breakout === 'breakdown')) $v = max($v, 0.9);
        if (($long && $breakout === 'near_breakout') || (!$long && $breakout === 'near_breakdown')) $v = max($v, 0.6);

        return round($v, 3);
    }

    protected function mtfFeature(array $bias, array $tfs, string $direction): float
    {
        $long = $direction === 'long' || $direction === 'buy';
        $dir = $bias['direction'] ?? 'neutral';
        $agreement = (int) ($bias['agreement'] ?? 0);
        $count = max(1, count($tfs));

        if (($long && $dir === 'long') || (!$long && $dir === 'short')) {
            return round((0.5 + ($agreement / 100) * 0.5) * min(1, $count / 2), 3);
        }
        return round((1 - ($agreement / 100) * 0.5) * 0.5, 3);
    }

    protected function volumeFeature(float $ratio, string $direction): float
    {
        if ($ratio >= 1.5) return 1.0;
        if ($ratio >= 1.1) return 0.75;
        if ($ratio >= 0.9) return 0.5;
        return 0.35;
    }

    protected function reasons(array $features): array
    {
        $reasons = [];
        if ($features['ema_align'] >= 2 / 3) $reasons[] = 'favourable EMA alignment';
        if ($features['macd'] >= 0.75) $reasons[] = 'MACD momentum expansion';
        if ($features['rsi_zone'] >= 0.75) $reasons[] = 'RSI in healthy momentum zone';
        if ($features['structure'] >= 0.75) $reasons[] = 'supportive market structure';
        if ($features['mtf'] >= 0.7) $reasons[] = 'strong multi-timeframe agreement';
        if ($features['volume'] >= 0.75) $reasons[] = 'volume confirmation above average';
        return $reasons;
    }
}