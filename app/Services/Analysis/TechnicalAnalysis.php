<?php

namespace App\Services\Analysis;

class TechnicalAnalysis
{
    public static function ema(array $data, int $period): array
    {
        $result = [];
        if (empty($data)) return $result;

        $multiplier = 2 / ($period + 1);
        $result[] = $data[0];

        for ($i = 1; $i < count($data); $i++) {
            $result[] = ($data[$i] - $result[$i - 1]) * $multiplier + $result[$i - 1];
        }
        return $result;
    }

    public static function sma(array $data, int $period): array
    {
        $result = [];
        for ($i = 0; $i < count($data); $i++) {
            if ($i < $period - 1) {
                $result[] = null;
                continue;
            }
            $sum = array_sum(array_slice($data, $i - $period + 1, $period));
            $result[] = $sum / $period;
        }
        return $result;
    }

    public static function rsi(array $data, int $period = 14): array
    {
        $result = [];
        $gains = [];
        $losses = [];

        for ($i = 1; $i < count($data); $i++) {
            $change = $data[$i] - $data[$i - 1];
            $gains[] = $change > 0 ? $change : 0;
            $losses[] = $change < 0 ? abs($change) : 0;
        }

        for ($i = 0; $i < count($gains); $i++) {
            if ($i < $period - 1) {
                $result[] = null;
                continue;
            }
            $avgGain = array_sum(array_slice($gains, $i - $period + 1, $period)) / $period;
            $avgLoss = array_sum(array_slice($losses, $i - $period + 1, $period)) / $period;
            if ($avgLoss == 0) {
                $result[] = 100;
            } else {
                $rs = $avgGain / $avgLoss;
                $result[] = 100 - (100 / (1 + $rs));
            }
        }
        return $result;
    }

    public static function macd(array $data, int $fast = 12, int $slow = 26, int $signal = 9): array
    {
        $emaFast = self::ema($data, $fast);
        $emaSlow = self::ema($data, $slow);
        $macdLine = [];
        for ($i = 0; $i < count($data); $i++) {
            $macdLine[] = $emaFast[$i] - $emaSlow[$i];
        }
        $signalLine = self::ema($macdLine, $signal);
        $histogram = [];
        for ($i = 0; $i < count($macdLine); $i++) {
            $histogram[] = $macdLine[$i] - $signalLine[$i];
        }
        return ['macd' => $macdLine, 'signal' => $signalLine, 'histogram' => $histogram];
    }

    public static function atr(array $highs, array $lows, array $closes, int $period = 14): array
    {
        if (empty($closes)) return [];

        $tr = [];
        $tr[] = $highs[0] - $lows[0];

        for ($i = 1; $i < count($closes); $i++) {
            $tr[] = max(
                $highs[$i] - $lows[$i],
                abs($highs[$i] - $closes[$i - 1]),
                abs($lows[$i] - $closes[$i - 1])
            );
        }
        return self::ema($tr, $period);
    }

    public static function bollingerBands(array $data, int $period = 20, float $stdDev = 2.0): array
    {
        $sma = self::sma($data, $period);
        $upper = [];
        $lower = [];

        for ($i = 0; $i < count($data); $i++) {
            if ($sma[$i] === null) {
                $upper[] = null;
                $lower[] = null;
                continue;
            }
            $slice = array_slice($data, max(0, $i - $period + 1), $period);
            $mean = $sma[$i];
            $variance = 0;
            foreach ($slice as $val) {
                $variance += pow($val - $mean, 2);
            }
            $std = sqrt($variance / count($slice));
            $upper[] = $mean + $stdDev * $std;
            $lower[] = $mean - $stdDev * $std;
        }
        return ['upper' => $upper, 'middle' => $sma, 'lower' => $lower];
    }

    public static function stochasticRsi(array $data, int $rsiPeriod = 14, int $kPeriod = 3, int $dPeriod = 3): array
    {
        $rsi = self::rsi($data, $rsiPeriod);
        $k = [];
        for ($i = 0; $i < count($rsi); $i++) {
            if ($rsi[$i] === null || $i < $kPeriod - 1) {
                $k[] = null;
                continue;
            }
            $slice = array_filter(array_slice($rsi, $i - $kPeriod + 1, $kPeriod), fn($v) => $v !== null);
            if (empty($slice)) { $k[] = null; continue; }
            $min = min($slice);
            $max = max($slice);
            $k[] = $max == $min ? 50 : (($rsi[$i] - $min) / ($max - $min)) * 100;
        }
        $d = self::sma(array_filter($k, fn($v) => $v !== null), $dPeriod);
        return ['k' => $k, 'd' => $d];
    }

    public static function obv(array $closes, array $volumes): array
    {
        if (empty($closes)) return [];

        $result = [$volumes[0]];
        for ($i = 1; $i < count($closes); $i++) {
            if ($closes[$i] > $closes[$i - 1]) {
                $result[] = $result[$i - 1] + $volumes[$i];
            } elseif ($closes[$i] < $closes[$i - 1]) {
                $result[] = $result[$i - 1] - $volumes[$i];
            } else {
                $result[] = $result[$i - 1];
            }
        }
        return $result;
    }

    public static function vwap(array $highs, array $lows, array $closes, array $volumes): array
    {
        $result = [];
        if (empty($closes)) return $result;

        $cumVol = 0;
        $cumTP = 0;
        for ($i = 0; $i < count($closes); $i++) {
            $tp = ($highs[$i] + $lows[$i] + $closes[$i]) / 3;
            $cumVol += $volumes[$i];
            $cumTP += $tp * $volumes[$i];
            $result[] = $cumVol > 0 ? $cumTP / $cumVol : $tp;
        }
        return $result;
    }

    public static function detectMarketStructure(array $highs, array $lows): array
    {
        $hh = $hl = $lh = $ll = 0;
        $trend = 'unknown';

        for ($i = 2; $i < count($highs); $i++) {
            if ($highs[$i] > $highs[$i - 1] && $highs[$i - 1] > $highs[$i - 2]) $hh++;
            if ($lows[$i] > $lows[$i - 1] && $lows[$i - 1] > $lows[$i - 2]) $hl++;
            if ($highs[$i] < $highs[$i - 1] && $highs[$i - 1] < $highs[$i - 2]) $lh++;
            if ($lows[$i] < $lows[$i - 1] && $lows[$i - 1] < $lows[$i - 2]) $ll++;
        }

        if ($hh > 0 && $hl > 0) $trend = 'bullish';
        elseif ($lh > 0 && $ll > 0) $trend = 'bearish';

        return [
            'higher_highs' => $hh, 'higher_lows' => $hl,
            'lower_highs' => $lh, 'lower_lows' => $ll,
            'trend' => $trend,
        ];
    }

    public static function detectMarketRegime(array $closes, array $volumes, int $period = 20): string
    {
        if (count($closes) < $period) return 'unknown';

        $returns = [];
        for ($i = 1; $i < count($closes); $i++) {
            $returns[] = ($closes[$i] - $closes[$i - 1]) / $closes[$i - 1];
        }

        $meanReturn = array_sum($returns) / count($returns);
        $variance = 0;
        foreach ($returns as $r) {
            $variance += pow($r - $meanReturn, 2);
        }
        $volatility = sqrt($variance / count($returns));

        $sma = self::sma($closes, $period);
        $filtered = array_values(array_filter($sma, fn($v) => $v !== null));
        $lastSma = !empty($filtered) ? end($filtered) : null;
        $lastClose = end($closes);

        $avgVolume = array_sum(array_slice($volumes, -$period)) / $period;
        $recentVolume = array_sum(array_slice($volumes, -3)) / 3;

        if ($volatility > 0.04) return 'high_volatility';
        if ($volatility < 0.01) return 'low_volatility';
        if ($lastClose > $lastSma * 1.01) return 'trending_up';
        if ($lastClose < $lastSma * 0.99) return 'trending_down';
        return 'ranging';
    }
}
