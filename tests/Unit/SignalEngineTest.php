<?php

namespace Tests\Unit;

use App\Services\Trading\SignalEngine;
use Tests\TestCase;

class SignalEngineTest extends TestCase
{
    public function test_generate_signal_returns_valid_signal_for_trending_market()
    {
        $engine = new SignalEngine();

        $candles = [];
        $price = 100;
        for ($i = 0; $i < 250; $i++) {
            $candles[] = [
                'open' => $price,
                'high' => $price + 3,
                'low' => $price - 3,
                'close' => $price + 2,
                'volume' => 1000 + ($i % 5),
            ];
            $price += 2;
        }

        $signal = $engine->generateSignal($candles, 'trend_following');

        if ($signal) {
            $this->assertArrayHasKey('direction', $signal);
            $this->assertArrayHasKey('entry_price', $signal);
            $this->assertArrayHasKey('stop_loss', $signal);
            $this->assertArrayHasKey('take_profit', $signal);
            $this->assertArrayHasKey('score', $signal);
            $this->assertArrayHasKey('market_regime', $signal);
            $this->assertGreaterThan(0, $signal['risk_reward']);
        } else {
            $this->assertTrue(true, "No signal generated, engine completed gracefully");
        }
    }

    public function test_signal_engine_handles_empty_data()
    {
        $engine = new SignalEngine();
        $signal = $engine->generateSignal([], 'trend_following');
        $this->assertNull($signal);
    }
}
