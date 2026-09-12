<?php

namespace Tests\Unit\Scanner;

use App\Models\ScannerConfig;
use App\Services\Scanner\AiEngine;
use App\Services\Scanner\IntervalMapper;
use App\Services\Scanner\SignalScorer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ScannerCoreTest extends TestCase
{
    protected function snapshot(array $overrides = []): array
    {
        $primary = $overrides['primary_analysis'] ?? [
            'ema9' => 110, 'ema21' => 105, 'ema50' => 100, 'close' => 112,
            'macd_hist' => 1.2, 'rsi' => 62, 'stoch_k' => 70, 'stoch_d' => 60,
            'volume_ratio' => 1.6,
        ];

        return array_merge([
            'primary' => '4h',
            'primary_analysis' => $primary,
            'bias' => ['direction' => 'long', 'agreement' => 100, 'long_tfs' => 3, 'short_tfs' => 0, 'count' => 3],
            'structure' => ['trend' => 'bullish'],
            'breakout' => 'breakout',
            'regime' => 'trending',
            'volatility_class' => 'normal',
            'atr_pct' => 2.0,
            'atr' => 2.2,
            'close' => 112,
            'volume_ratio' => 1.6,
            'pivot_high' => 120,
            'pivot_low' => 105,
            'timeframes' => ['4h' => [], '1h' => [], '15m' => []],
            'timeframes_count' => 3,
        ], $overrides);
    }

    #[Test]
    public function interval_mapper_converts_exchange_specific_intervals(): void
    {
        $this->assertSame('4h', IntervalMapper::canonical('mexc', '4h'));
        $this->assertSame('240', IntervalMapper::canonical('bybit', '4h'));
        $this->assertSame('15', IntervalMapper::canonical('bybit', '15m'));
        $this->assertSame('D', IntervalMapper::canonical('bybit', '1d'));
        $this->assertTrue(IntervalMapper::supports('bybit', '1h'));
        $this->assertFalse(IntervalMapper::supports('bybit', '8h'));
    }

    #[Test]
    public function ai_engine_returns_honest_probability_and_reasons(): void
    {
        $engine = new AiEngine();
        $long = $engine->predict($this->snapshot(), 'long');
        $short = $engine->predict($this->snapshot(), 'short');

        $this->assertGreaterThanOrEqual(1.0, $long['probability']);
        $this->assertLessThanOrEqual(99.0, $long['probability']);
        $this->assertGreaterThan($short['probability'], $long['probability']);
        $this->assertNotEmpty($long['reasons']);
        $this->assertSame(['ema_align', 'macd', 'rsi_zone', 'structure', 'mtf', 'volume'], array_keys($long['features']));
    }

    #[Test]
    public function ai_engine_downweight_neutral_or_contradicting_signals(): void
    {
        $engine = new AiEngine();

        $bearish = $this->snapshot([
            'primary_analysis' => [
                'ema9' => 90, 'ema21' => 95, 'ema50' => 100, 'close' => 88,
                'macd_hist' => -1.1, 'rsi' => 38, 'stoch_k' => 30, 'stoch_d' => 40,
                'volume_ratio' => 0.7,
            ],
            'structure' => ['trend' => 'bearish'],
            'breakout' => 'breakdown',
            'bias' => ['direction' => 'short', 'agreement' => 100, 'long_tfs' => 0, 'short_tfs' => 3, 'count' => 3],
        ]);

        $short = $engine->predict($bearish, 'short');
        $long = $engine->predict($bearish, 'long');

        $this->assertGreaterThan($long['probability'], $short['probability']);
    }

    #[Test]
    public function signal_scorer_weights_sum_to_one_and_score_bounded(): void
    {
        $config = new ScannerConfig(['min_signal_score' => 75, 'score_weights' => null]);
        $scorer = new SignalScorer(new AiEngine());

        $result = $scorer->score($this->snapshot(), 50_000_000, $config);

        $this->assertGreaterThanOrEqual(0, $result['score']);
        $this->assertLessThanOrEqual(100, $result['score']);
        $this->assertSame('long', $result['direction']);

        $sum = array_sum($result['breakdown']['weights']);
        $this->assertEqualsWithDelta(1.0, $sum, 0.0001);

        foreach (['trend', 'momentum', 'volume', 'structure', 'mtf', 'ai'] as $component) {
            $this->assertArrayHasKey($component, $result['breakdown']['components']);
        }
        $this->assertArrayHasKey('probability', $result['ai']);
    }

    #[Test]
    public function signal_scorer_neutral_direction_scores_zero(): void
    {
        $config = new ScannerConfig([]);
        $scorer = new SignalScorer(new AiEngine());

        $neutral = $this->snapshot([
            'bias' => ['direction' => 'neutral', 'agreement' => 50, 'count' => 3],
            'structure' => ['trend' => 'ranging'],
            'breakout' => 'none',
        ]);

        $result = $scorer->score($neutral, 5_000_000, $config);
        $this->assertSame(0, $result['score']);
        $this->assertSame('neutral', $result['direction']);
    }

    #[Test]
    public function strongly_bullish_snapshot_outranks_weak_one(): void
    {
        $config = new ScannerConfig([]);
        $scorer = new SignalScorer(new AiEngine());

        $strong = $scorer->score($this->snapshot(), 500_000_000, $config)['score'];
        $weak = $scorer->score($this->snapshot([
            'primary_analysis' => [
                'ema9' => 101, 'ema21' => 100, 'ema50' => 102, 'close' => 101.5,
                'macd_hist' => 0.02, 'rsi' => 52, 'stoch_k' => 51, 'stoch_d' => 50,
                'volume_ratio' => 1.0,
            ],
            'structure' => ['trend' => 'ranging'],
            'breakout' => 'none',
            'bias' => ['direction' => 'long', 'agreement' => 33, 'count' => 3],
        ]), 1_000_000, $config)['score'];

        $this->assertGreaterThan($weak, $strong);
    }
}