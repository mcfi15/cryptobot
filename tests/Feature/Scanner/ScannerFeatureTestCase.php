<?php

namespace Tests\Feature\Scanner;

use App\Models\ExchangeAccount;
use App\Models\ExchangeMarket;
use App\Models\ScannerConfig;
use App\Models\ScannerSignal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

abstract class ScannerFeatureTestCase extends TestCase
{
    use RefreshDatabase;

    protected function fakeBalances(float $usdt = 10000): void
    {
        Http::fake(['*' => Http::response([
            'balances' => [['asset' => 'USDT', 'free' => (string) $usdt, 'locked' => '0']],
        ])]);
    }

    protected function makeUserAndAccount(string $exchange = 'mexc'): array
    {
        $user = User::factory()->create();
        $account = ExchangeAccount::create([
            'user_id' => $user->id,
            'exchange' => $exchange,
            'label' => 'Test Account',
            'encrypted_credentials' => encrypt(json_encode(['api_key' => 'k', 'api_secret' => 's'])),
            'status' => 'connected',
            'trading_enabled' => true,
        ]);
        return [$user, $account];
    }

    protected function makeConfig(int $userId, array $overrides = []): ScannerConfig
    {
        return ScannerConfig::factory()->create(array_merge(['user_id' => $userId], $overrides));
    }

    protected function makeMarket(string $exchange = 'mexc', string $symbol = 'BTCUSDT', string $marketType = 'spot'): ExchangeMarket
    {
        return ExchangeMarket::create([
            'exchange' => $exchange,
            'symbol' => $symbol,
            'base_asset' => 'BTC',
            'quote_asset' => 'USDT',
            'market_type' => $marketType,
            'price_precision' => 6,
            'quantity_precision' => 6,
            'min_quantity' => '0.0001',
            'max_quantity' => '100000',
            'tick_size' => '0.01',
            'min_notional' => '5',
            'leverage_limit' => 10,
            'status' => 'trading',
        ]);
    }

    protected function makeSignal(int $userId, int $configId, int $accountId, array $overrides = []): ScannerSignal
    {
        return ScannerSignal::factory()->create(array_merge([
            'user_id' => $userId,
            'config_id' => $configId,
            'exchange_account_id' => $accountId,
            'exchange' => 'mexc',
            'symbol' => 'BTCUSDT',
            'market_type' => 'spot',
            'status' => 'qualified',
            'base_asset' => 'BTC',
            'quote_asset' => 'USDT',
        ], $overrides));
    }
}