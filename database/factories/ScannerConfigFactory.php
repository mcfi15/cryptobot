<?php

namespace Database\Factories;

use App\Models\ScannerConfig;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScannerConfigFactory extends Factory
{
    protected $model = ScannerConfig::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'scan_mode' => 'all',
            'exchanges' => null,
            'market_type' => 'both',
            'min_signal_score' => 75,
            'min_ai_probability' => 70.00,
            'min_risk_reward' => 1.50,
            'min_volume_24h' => 1000000.00,
            'max_spread_pct' => 0.500,
            'max_volatility' => 'high',
            'max_markets' => 50,
            'timeframes' => ['4h', '1h', '15m'],
            'preferred_assets' => null,
            'quote_assets' => ['USDT'],
            'auto_trading' => false,
            'risk_per_trade' => 0.50,
            'max_daily_loss' => 2.00,
            'max_open_positions' => 3,
            'max_leverage' => 20,
            'signal_expiry_minutes' => 30,
            'trading_mode' => 'paper',
            'paper_mode' => true,
            'status' => 'stopped',
        ];
    }

    public function running(): static
    {
        return $this->state(fn () => ['status' => 'running']);
    }

    public function autoTrading(): static
    {
        return $this->state(fn () => ['auto_trading' => true, 'status' => 'running']);
    }
}