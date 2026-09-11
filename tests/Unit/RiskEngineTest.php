<?php

namespace Tests\Unit;

use App\Services\Risk\SpotRiskEngine;
use App\Services\Risk\FuturesRiskEngine;
use App\Models\TradingBot;
use App\Models\User;
use App\Models\ExchangeAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiskEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function makeBot(string $marketType, int $maxLeverage = 1): TradingBot
    {
        $user = User::factory()->create();
        $exchangeAccount = ExchangeAccount::create([
            'user_id' => $user->id,
            'exchange' => 'mexc',
            'label' => 'Test',
            'encrypted_credentials' => encrypt(json_encode(['api_key' => 'k', 'api_secret' => 's'])),
            'status' => 'connected',
        ]);

        return TradingBot::create([
            'user_id' => $user->id,
            'exchange_account_id' => $exchangeAccount->id,
            'name' => 'Test Bot',
            'market_type' => $marketType,
            'mode' => 'paper',
            'strategy' => 'trend_following',
            'symbols' => ['BTCUSDT'],
            'timeframes' => ['4h'],
            'risk_per_trade' => 0.5,
            'max_daily_loss' => 2.0,
            'max_open_positions' => 5,
            'max_position_size' => 1000,
            'max_leverage' => $maxLeverage,
            'status' => 'stopped',
        ]);
    }

    public function test_spot_risk_engine_allows_valid_trade()
    {
        $bot = $this->makeBot('spot');

        $engine = new SpotRiskEngine();
        $signal = [
            'entry_price' => 100,
            'stop_loss' => 99,
            'take_profit' => 102,
        ];

        $result = $engine->checkTrade($bot, $signal, 10000);
        $this->assertTrue($result['allowed']);
        $this->assertGreaterThan(0, $result['position_size']);
    }

    public function test_futures_risk_engine_blocks_risky_leverage()
    {
        $bot = $this->makeBot('futures', 20);

        $engine = new FuturesRiskEngine();
        $signal = [
            'entry_price' => 100,
            'stop_loss' => 99,
            'take_profit' => 102,
            'leverage' => 100,
        ];

        $result = $engine->checkTrade($bot, $signal, 10000);
        $this->assertFalse($result['allowed']);
        $this->assertArrayHasKey('reason', $result);
    }
}
