<?php

namespace Tests\Feature\Scanner;

use App\Models\BotPosition;
use App\Models\BotTrade;
use App\Models\GlobalSetting;
use App\Models\ScannerConfig;
use App\Services\Scanner\ScannerRiskEngine;
use App\Services\Scanner\ScannerService;
use App\Services\Scanner\SignalMonitor;
use App\Services\Scanner\SignalRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class ScannerSafetyAndExitTest extends ScannerFeatureTestCase
{
    // ---------- Risk engine gates ------------------------------------------------

    public function test_emergency_stop_blocks_all_trades(): void
    {
        [$user, $account] = $this->makeUserAndAccount();
        $config = $this->makeConfig($user->id, ['status' => 'running']);
        $this->fakeBalances(5000);

        GlobalSetting::set('emergency_stop', true);

        $result = app(ScannerRiskEngine::class)->evaluate($account, [
            'symbol' => 'BTCUSDT', 'base_asset' => 'BTC', 'entry_price' => 100, 'current_price' => 100,
        ], $config, manual: true);

        $this->assertFalse($result['pass']);
        $this->assertStringContainsString('emergency stop', strtolower(implode(' ', $result['failures'])));
    }

    public function test_paused_config_blocks_auto_but_manual_passes(): void
    {
        [$user, $account] = $this->makeUserAndAccount();
        $config = $this->makeConfig($user->id, ['status' => 'paused', 'paused_reason' => 'drawdown']);
        $this->fakeBalances(5000);

        $auto = app(ScannerRiskEngine::class)->evaluate($account, [
            'symbol' => 'BTCUSDT', 'base_asset' => 'BTC', 'entry_price' => 100, 'current_price' => 100,
        ], $config, manual: false);

        $manual = app(ScannerRiskEngine::class)->evaluate($account, [
            'symbol' => 'BTCUSDT', 'base_asset' => 'BTC', 'entry_price' => 100, 'current_price' => 100,
        ], $config, manual: true);

        $this->assertFalse($auto['pass']);
        $this->assertTrue($manual['pass']);
    }

    public function test_global_risk_cap_exceeded(): void
    {
        [$user, $account] = $this->makeUserAndAccount();
        $config = $this->makeConfig($user->id, ['status' => 'running', 'risk_per_trade' => 20]);
        $this->fakeBalances(5000);

        GlobalSetting::set('max_risk_per_trade', 5);

        $result = app(ScannerRiskEngine::class)->evaluate($account, [
            'symbol' => 'BTCUSDT', 'base_asset' => 'BTC', 'entry_price' => 100, 'current_price' => 100,
        ], $config, manual: true);

        $this->assertFalse($result['pass']);
        $this->assertStringContainsString('risk per trade', strtolower(implode(' ', $result['failures'])));
    }

    public function test_liquidation_distance_too_close_blocks_futures(): void
    {
        [$user, $account] = $this->makeUserAndAccount();
        $config = $this->makeConfig($user->id, ['status' => 'running', 'max_leverage' => 10]);
        $this->fakeBalances(5000);

        GlobalSetting::set('liquidation_distance_min_pct', 10);

        $result = app(ScannerRiskEngine::class)->evaluate($account, [
            'symbol' => 'BTCUSDT', 'base_asset' => 'BTC', 'entry_price' => 100,
            'stop_loss' => 99, 'current_price' => 100, 'direction' => 'long',
            'market_type' => 'futures', 'leverage_limit' => 10,
        ], $config, manual: true);

        $this->assertFalse($result['pass']);
        $this->assertStringContainsString('liquidation distance', strtolower(implode(' ', $result['failures'])));
    }

    public function test_cooldown_blocks_same_symbol(): void
    {
        [$user, $account] = $this->makeUserAndAccount();
        $config = $this->makeConfig($user->id, ['status' => 'running', 'cooldown_minutes' => 30]);
        $this->fakeBalances(5000);

        BotTrade::create([
            'user_id' => $user->id,
            'exchange_account_id' => $account->id,
            'exchange' => 'mexc',
            'market_type' => 'spot',
            'symbol' => 'BTCUSDT',
            'side' => 'buy',
            'entry_price' => 100,
            'quantity' => 1,
            'leverage' => 1,
            'status' => 'closed',
            'pnl' => -5,
            'closed_at' => now()->subMinutes(10),
        ]);

        $result = app(ScannerRiskEngine::class)->evaluate($account, [
            'symbol' => 'BTCUSDT', 'base_asset' => 'BTC', 'entry_price' => 100, 'current_price' => 100,
        ], $config, manual: true);

        $this->assertFalse($result['pass']);
        $this->assertStringContainsString('cooldown', strtolower(implode(' ', $result['failures'])));
    }

    public function test_consecutive_losses_block_trading(): void
    {
        [$user, $account] = $this->makeUserAndAccount();
        $config = $this->makeConfig($user->id, ['status' => 'running', 'max_consecutive_losses' => 3]);
        $this->fakeBalances(5000);

        for ($i = 0; $i < 3; $i++) {
            BotTrade::create([
                'user_id' => $user->id,
                'exchange_account_id' => $account->id,
                'exchange' => 'mexc',
                'market_type' => 'spot',
                'symbol' => 'BTCUSDT',
                'side' => 'buy',
                'entry_price' => 100,
                'quantity' => 1,
                'leverage' => 1,
                'status' => 'closed',
                'pnl' => -10 - $i,
                'closed_at' => now()->subMinutes(120 - $i * 10),
            ]);
        }

        $result = app(ScannerRiskEngine::class)->evaluate($account, [
            'symbol' => 'ETHUSDT', 'base_asset' => 'ETH', 'entry_price' => 200, 'current_price' => 200,
        ], $config, manual: true);

        $this->assertFalse($result['pass']);
        $this->assertStringContainsString('consecutive', strtolower(implode(' ', $result['failures'])));
    }

    public function test_correlated_exposure_cap(): void
    {
        [$user, $account] = $this->makeUserAndAccount();
        $config = $this->makeConfig($user->id, [
            'status' => 'running', 'risk_per_trade' => 2, 'max_leverage' => 1, 'max_open_positions' => 10,
        ]);
        $this->fakeBalances(5000);
        GlobalSetting::set('correlated_assets', 'BTC,ETH');
        GlobalSetting::set('max_correlated_exposure', 5);

        // Open a BTC position consuming most of the cap.
        BotPosition::create([
            'user_id' => $user->id, 'exchange_account_id' => $account->id,
            'bot_id' => null, 'signal_id' => null, 'exchange' => 'mexc', 'market_type' => 'spot',
            'symbol' => 'BTCUSDT', 'side' => 'buy', 'quantity' => 0.1, 'entry_price' => 25000,
            'current_price' => 25000, 'leverage' => 1, 'margin' => 2500, 'status' => 'open', 'opened_at' => now(),
        ]);

        // New ETH trade should fail correlated exposure.
        $result = app(ScannerRiskEngine::class)->evaluate($account, [
            'symbol' => 'ETHUSDT', 'base_asset' => 'ETH', 'entry_price' => 2000, 'stop_loss' => 1950, 'current_price' => 2000,
        ], $config, manual: true);

        $this->assertFalse($result['pass']);
        $this->assertStringContainsString('correlated exposure', strtolower(implode(' ', $result['failures'])));
    }

    // ---------- Auto-pause on drawdown ------------------------------------------

    public function test_apply_risk_protection_pauses_on_drawdown(): void
    {
        [$user, $account] = $this->makeUserAndAccount();
        $config = $this->makeConfig($user->id, ['status' => 'running', 'peak_equity' => 1000]);
        $this->fakeBalances(800);

        GlobalSetting::set('global_max_drawdown', 10);

        $service = app(ScannerService::class);
        $method = new \ReflectionMethod(ScannerService::class, 'applyRiskProtection');
        $method->setAccessible(true);

        $paused = $method->invoke($service, $user, $config);

        $this->assertTrue($paused);
        $config->refresh();
        $this->assertSame('paused', $config->status);
        $this->assertNotNull($config->paused_reason);
        $this->assertStringContainsString('Drawdown', $config->paused_reason);
    }

    // ---------- Exit management (SignalMonitor) -----------------------------------

    public function test_trailing_stop_closes_position_on_profit_pullback(): void
    {
        [$user, $account] = $this->makeUserAndAccount();
        $config = $this->makeConfig($user->id, [
            'status' => 'running', 'paper_mode' => true,
            'trailing_enabled' => true, 'trailing_activation_pct' => 1.0, 'trailing_distance_pct' => 0.5,
        ]);

        $signal = $this->makeSignal($user->id, $config->id, $account->id, [
            'entry_price' => 100, 'stop_loss' => 99, 'take_profit' => 110, 'direction' => 'long',
            'status' => 'executed', 'watch' => true,
        ]);

        BotPosition::create([
            'user_id' => $user->id, 'exchange_account_id' => $account->id,
            'bot_id' => null, 'signal_id' => $signal->id, 'exchange' => 'mexc', 'market_type' => 'spot',
            'symbol' => 'BTCUSDT', 'side' => 'buy', 'quantity' => 1, 'entry_price' => 100,
            'current_price' => 102.5, 'leverage' => 1, 'margin' => 100, 'stop_loss' => 99, 'take_profit' => 110,
            'status' => 'open', 'opened_at' => now(), 'trail_high' => 102.5, 'trail_low' => null,
        ]);

        BotTrade::create([
            'user_id' => $user->id, 'exchange_account_id' => $account->id,
            'signal_id' => $signal->id, 'source' => 'scanner', 'bot_id' => null,
            'exchange' => 'mexc', 'market_type' => 'spot', 'symbol' => 'BTCUSDT', 'side' => 'buy',
            'entry_price' => 100, 'quantity' => 1, 'leverage' => 1, 'margin' => 100,
            'stop_loss' => 99, 'take_profit' => 110, 'status' => 'simulated', 'opened_at' => now(),
        ]);

        // Price pulls back after activating trailing: high was 101.5 (1.5% profit activates),
        // now price is 100.9 (101.5 * (1 - 0.5/100) = 100.9925), so trailing_stop triggers.
        Http::fake(['*' => Http::response([
            'symbol' => 'BTCUSDT', 'lastPrice' => '100.9', 'bidPrice' => '100.8', 'askPrice' => '101.0', 'volume' => '1',
            'highPrice' => '102.5', 'lowPrice' => '100.5', 'priceChangePercent' => '0',
        ])]);

        $monitor = app(SignalMonitor::class);
        $result = $monitor->monitor(dryRun: false);

        $this->assertGreaterThanOrEqual(1, $result['closed']);
        $position = BotPosition::where('signal_id', $signal->id)->first();
        $this->assertSame('closed', $position->status);
        $trade = BotTrade::where('signal_id', $signal->id)->where('status', 'closed')->first();
        $this->assertNotNull($trade);
        $this->assertSame('trailing_stop', $trade->exit_reason);
    }

    public function test_max_hold_time_closes_position(): void
    {
        [$user, $account] = $this->makeUserAndAccount();
        $config = $this->makeConfig($user->id, [
            'status' => 'running', 'paper_mode' => true, 'max_hold_hours' => 24,
        ]);

        $signal = $this->makeSignal($user->id, $config->id, $account->id, [
            'entry_price' => 100, 'stop_loss' => 95, 'take_profit' => 120, 'direction' => 'long',
            'status' => 'executed', 'watch' => true,
        ]);

        BotPosition::create([
            'user_id' => $user->id, 'exchange_account_id' => $account->id,
            'bot_id' => null, 'signal_id' => $signal->id, 'exchange' => 'mexc', 'market_type' => 'spot',
            'symbol' => 'BTCUSDT', 'side' => 'buy', 'quantity' => 1, 'entry_price' => 100,
            'current_price' => 105, 'leverage' => 1, 'margin' => 100, 'stop_loss' => 95, 'take_profit' => 120,
            'status' => 'open', 'opened_at' => now()->subHours(25), 'trail_high' => 105, 'trail_low' => null,
        ]);

        BotTrade::create([
            'user_id' => $user->id, 'exchange_account_id' => $account->id,
            'signal_id' => $signal->id, 'source' => 'scanner', 'bot_id' => null,
            'exchange' => 'mexc', 'market_type' => 'spot', 'symbol' => 'BTCUSDT', 'side' => 'buy',
            'entry_price' => 100, 'quantity' => 1, 'leverage' => 1, 'margin' => 100,
            'stop_loss' => 95, 'take_profit' => 120, 'status' => 'simulated', 'opened_at' => now()->subHours(25),
        ]);

        Http::fake(['*' => Http::response([
            'symbol' => 'BTCUSDT', 'lastPrice' => '105', 'bidPrice' => '104.9', 'askPrice' => '105.1', 'volume' => '1',
            'highPrice' => '105', 'lowPrice' => '100', 'priceChangePercent' => '0',
        ])]);

        $monitor = app(SignalMonitor::class);
        $result = $monitor->monitor(dryRun: false);

        $this->assertGreaterThanOrEqual(1, $result['closed']);
        $trade = BotTrade::where('signal_id', $signal->id)->where('status', 'closed')->first();
        $this->assertSame('time_exit', $trade->exit_reason);
    }

    public function test_dry_run_does_not_close(): void
    {
        [$user, $account] = $this->makeUserAndAccount();
        $config = $this->makeConfig($user->id, [
            'status' => 'running', 'paper_mode' => true, 'max_hold_hours' => 24,
        ]);

        $signal = $this->makeSignal($user->id, $config->id, $account->id, [
            'entry_price' => 100, 'stop_loss' => 95, 'take_profit' => 120, 'direction' => 'long',
            'status' => 'executed', 'watch' => true,
        ]);

        BotPosition::create([
            'user_id' => $user->id, 'exchange_account_id' => $account->id,
            'bot_id' => null, 'signal_id' => $signal->id, 'exchange' => 'mexc', 'market_type' => 'spot',
            'symbol' => 'BTCUSDT', 'side' => 'buy', 'quantity' => 1, 'entry_price' => 100,
            'current_price' => 105, 'leverage' => 1, 'margin' => 100, 'stop_loss' => 95, 'take_profit' => 120,
            'status' => 'open', 'opened_at' => now()->subHours(25), 'trail_high' => 105,
        ]);

        Http::fake(['*' => Http::response([
            'symbol' => 'BTCUSDT', 'lastPrice' => '105', 'bidPrice' => '104.9', 'askPrice' => '105.1', 'volume' => '1',
            'highPrice' => '105', 'lowPrice' => '100', 'priceChangePercent' => '0',
        ])]);

        $monitor = app(SignalMonitor::class);
        $result = $monitor->monitor(dryRun: true);

        $this->assertGreaterThanOrEqual(1, $result['processed']);
        $this->assertSame(0, $result['closed']);
        $this->assertSame('open', BotPosition::where('signal_id', $signal->id)->first()->status);
    }
}

