<?php

namespace Database\Factories;

use App\Models\ScannerConfig;
use App\Models\ScannerSignal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScannerSignalFactory extends Factory
{
    protected $model = ScannerSignal::class;

    public function definition(): array
    {
        $direction = $this->faker->randomElement(['long', 'short']);
        $entry = $this->faker->randomFloat(6, 0.01, 2000);

        return [
            'user_id' => User::factory(),
            'config_id' => ScannerConfig::factory(),
            'exchange_account_id' => null,
            'exchange' => $this->faker->randomElement(['mexc', 'bybit', 'binance']),
            'symbol' => strtoupper($this->faker->bothify('?###USDT')),
            'base_asset' => null,
            'quote_asset' => 'USDT',
            'market_type' => $this->faker->randomElement(['spot', 'futures']),
            'direction' => $direction,
            'status' => 'qualified',
            'signal_score' => $this->faker->numberBetween(55, 98),
            'ai_probability' => $this->faker->randomFloat(2, 45, 96),
            'entry_price' => $entry,
            'stop_loss' => $direction === 'long' ? $entry * 0.98 : $entry * 1.02,
            'take_profit' => $direction === 'long' ? $entry * 1.04 : $entry * 0.96,
            'risk_reward' => 2.0,
            'current_price' => $entry,
            'volume_24h' => $this->faker->randomFloat(2, 1_000_000, 50_000_000),
            'spread_pct' => 0.1,
            'volatility_class' => 'normal',
            'timeframe' => '4h',
            'strategy' => 'technical',
            'market_regime' => 'trending',
            'score_quality' => 'strong',
            'score_breakdown' => null,
            'watch' => false,
            'fingerprint' => md5(random_bytes(16)),
            'expires_at' => now()->addMinutes(30),
            'outcome' => 'pending',
        ];
    }

    public function quality(string $quality): static
    {
        return $this->state(fn () => ['score_quality' => $quality]);
    }

    public function status(string $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}