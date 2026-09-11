<?php

namespace Tests\Unit;

use App\Services\Analysis\TechnicalAnalysis;
use Tests\TestCase;

class TechnicalAnalysisTest extends TestCase
{
    protected array $closes;
    protected array $highs;
    protected array $lows;
    protected array $volumes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->closes = [];
        $this->highs = [];
        $this->lows = [];
        $this->volumes = [];

        $price = 100;
        for ($i = 0; $i < 50; $i++) {
            $this->closes[] = $price;
            $this->highs[] = $price + 2;
            $this->lows[] = $price - 2;
            $this->volumes[] = 1000;
            $price += 2;
        }
    }

    public function test_ema_returns_correct_length()
    {
        $ema = TechnicalAnalysis::ema($this->closes, 9);
        $this->assertCount(count($this->closes), $ema);
        $this->assertNotNull(end($ema));
    }

    public function test_rsi_returns_values_between_zero_and_hundred()
    {
        $rsi = TechnicalAnalysis::rsi($this->closes);
        $last = end($rsi);
        $this->assertGreaterThanOrEqual(0, $last);
        $this->assertLessThanOrEqual(100, $last);
    }

    public function test_macd_returns_histogram()
    {
        $macd = TechnicalAnalysis::macd($this->closes);
        $this->assertArrayHasKey('macd', $macd);
        $this->assertArrayHasKey('signal', $macd);
        $this->assertArrayHasKey('histogram', $macd);
    }

    public function test_atr_is_positive()
    {
        $atr = TechnicalAnalysis::atr($this->highs, $this->lows, $this->closes);
        $this->assertGreaterThan(0, end($atr));
    }

    public function test_detect_market_regime_returns_valid_value()
    {
        $regime = TechnicalAnalysis::detectMarketRegime($this->closes, $this->volumes);
        $this->assertContains($regime, ['trending_up', 'trending_down', 'ranging', 'high_volatility', 'low_volatility', 'unknown']);
    }

    public function test_market_structure_detection()
    {
        $structure = TechnicalAnalysis::detectMarketStructure($this->highs, $this->lows);
        $this->assertArrayHasKey('trend', $structure);
        $this->assertArrayHasKey('higher_highs', $structure);
        $this->assertArrayHasKey('lower_lows', $structure);
    }
}
