<?php

namespace Tests\Feature\Scanner;

use App\Jobs\ProcessScannerSignal;
use App\Models\BotPosition;
use App\Models\GlobalSetting;
use App\Models\ScannerConfig;
use App\Services\Scanner\ScannerExecutionService;
use App\Services\Scanner\ScannerRiskEngine;

class ScannerRiskEngineTest extends ScannerFeatureTestCase
{
    public function test_kill_switch_blocks_auto_execution(): void
    {
        [$user, $account] = $this->makeUserAndAccount();
        $config = $this->makeConfig($user->id, ['status' => 'running', 'auto_trading' => true]);
        $this->fakeBalances(1000);

        GlobalSetting::set('global_trading_kill_switch', true);

        $result = app(ScannerRiskEngine::class)->evaluate($account, [
            'symbol' => 'BTCUSDT', 'base_asset' => 'BTC', 'entry_price' => 100, 'current_price' => 100,
        ], $config);

        $this->assertFalse($result['pass']);
        $this->assertStringContainsString('kill switch', strtolower(implode(' ', $result['failures'])));
    }

    public function test_manual_trade_allowed_when_scanner_stopped(): void
    {
        [$user, $account] = $this->makeUserAndAccount();
        $config = $this->makeConfig($user->id, ['status' => 'stopped', 'auto_trading' => false]);
        $this->fakeBalances(1000);

        $result = app(ScannerRiskEngine::class)->evaluate($account, [
            'symbol' => 'BTCUSDT', 'base_asset' => 'BTC', 'entry_price' => 100, 'current_price' => 100,
        ], $config, manual: true);

        $this->assertTrue($result['pass']);
    }

    public function test_open_position_on_same_symbol_blocks_new_trade(): void
    {
        [$user, $account] = $this->makeUserAndAccount();
        $config = $this->makeConfig($user->id, ['status' => 'stopped']);
        $this->fakeBalances(1000);

        BotPosition::create([
            'user_id' => $user->id,
            'exchange_account_id' => $account->id,
            'bot_id' => null,
            'signal_id' => null,
            'exchange' => 'mexc',
            'market_type' => 'spot',
            'symbol' => 'BTCUSDT',
            'side' => 'buy',
            'quantity' => 0.01,
            'entry_price' => 100,
            'current_price' => 100,
            'leverage' => 1,
            'margin' => 1,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $result = app(ScannerRiskEngine::class)->evaluate($account, [
            'symbol' => 'BTCUSDT', 'base_asset' => 'BTC', 'entry_price' => 100, 'current_price' => 100,
        ], $config, manual: true);

        $this->assertFalse($result['pass']);
        $this->assertStringContainsString('already open', strtolower(implode(' ', $result['failures'])));
    }

    public function test_minimum_balance_check(): void
    {
        [$user, $account] = $this->makeUserAndAccount();
        $config = $this->makeConfig($user->id, ['status' => 'stopped']);
        $this->fakeBalances(2);

        $result = app(ScannerRiskEngine::class)->evaluate($account, [
            'symbol' => 'BTCUSDT', 'base_asset' => 'BTC', 'entry_price' => 100, 'current_price' => 100,
        ], $config, manual: true);

        $this->assertFalse($result['pass']);
        $this->assertStringContainsString('minimum trading threshold', strtolower(implode(' ', $result['failures'])));
    }

    public function test_paper_execution_creates_trade_and_position(): void
    {
        [$user, $account] = $this->makeUserAndAccount();
        $config = $this->makeConfig($user->id, ['status' => 'running', 'paper_mode' => true, 'trading_mode' => 'paper']);
        $market = $this->makeMarket();
        $signal = $this->makeSignal($user->id, $config->id, $account->id, [
            'entry_price' => 100, 'stop_loss' => 99, 'take_profit' => 104,
            'current_price' => 100, 'risk_reward' => 4,
        ]);
        $this->fakeBalances(10000);

        $result = app(ScannerExecutionService::class)->execute($signal, $account, $market, $config);

        $this->assertTrue($result['ok']);
        $this->assertDatabaseHas('bot_trades', ['signal_id' => $signal->id, 'source' => 'scanner', 'status' => 'simulated']);
        $this->assertDatabaseHas('bot_positions', ['signal_id' => $signal->id, 'status' => 'open']);
        $this->assertSame('executed', $signal->fresh()->status);
        $this->assertSame('executed', $result['trade']->status);
    }

    public function test_execution_rejects_below_min_notional(): void
    {
        [$user, $account] = $this->makeUserAndAccount();
        $config = $this->makeConfig($user->id, ['status' => 'running']);
        $market = $this->makeMarket();
        $signal = $this->makeSignal($user->id, $config->id, $account->id, [
            'entry_price' => 0.5, 'stop_loss' => 0.49, 'take_profit' => 0.55,
            'current_price' => 0.5, 'risk_reward' => 5,
        ]);
        $this->fakeBalances(50);

        $result = app(ScannerExecutionService::class)->execute($signal, $account, $market, $config);

        $this->assertFalse($result['ok']);
        $this->assertDatabaseMissing('bot_trades', ['signal_id' => $signal->id]);
    }

    public function test_auto_execution_job_never_bypasses_risk_engine(): void
    {
        [$user, $account] = $this->makeUserAndAccount();
        $config = $this->makeConfig($user->id, ['status' => 'running', 'auto_trading' => true]);
        $this->makeMarket();
        $signal = $this->makeSignal($user->id, $config->id, $account->id);
        $this->fakeBalances(1000);

        GlobalSetting::set('scanner_kill_switch', true);

        ProcessScannerSignal::dispatch($signal->id);

        $signal->refresh();
        $this->assertSame('rejected', $signal->status);
        $this->assertNotNull($signal->invalidation['reason'] ?? null);
        $this->assertStringContainsString('kill switch', strtolower($signal->invalidation['reason'] ?? ''));
        $this->assertDatabaseMissing('bot_trades', ['signal_id' => $signal->id]);
    }

    public function test_signal_repository_detects_duplicates_and_expires(): void
    {
        [$user, $account] = $this->makeUserAndAccount();
        $config = $this->makeConfig($user->id, ['status' => 'running']);
        $repo = app(\App\Services\Scanner\SignalRepository::class);

        $accountMap = ['id' => $account->id];
        $analysis = ['ai' => ['probability' => 80, 'reasons' => ['x']], 'close' => 100, 'volume_24h' => 5000000, 'spread_pct' => 0.1, 'volatility_class' => 'normal', 'regime' => 'trending', 'breakdown' => [], 'timeframes' => []];

        $sig = $repo->create($user->id, $config->id, $accountMap, 'mexc', 'ETHUSDT', 'ETH', 'USDT', 'spot', 'long', '4h', 'technical', 100, 99, 104, 4, 88, 'strong', $analysis, $config);

        $this->assertTrue($repo->isDuplicate($repo->fingerprint('mexc', 'ETHUSDT', 'long', '4h')));

        $sig->update(['expires_at' => now()->subMinute(), 'status' => 'qualified']);
        $this->assertSame(1, $repo->expireDue());
        $this->assertFalse($repo->isDuplicate($repo->fingerprint('mexc', 'ETHUSDT', 'long', '4h')));
    }

    public function test_scanner_config_for_user_creates_defaults(): void
    {
        $user = \App\Models\User::factory()->create();

        $config = ScannerConfig::forUser($user->id);

        $this->assertSame('stopped', $config->status);
        $this->assertSame(['4h', '1h', '15m'], $config->timeframeList());
    }
}