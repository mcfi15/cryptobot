<?php

namespace Tests\Feature\Scanner;

use App\Models\GlobalSetting;
use App\Models\ScannerConfig;
use App\Models\ScannerSignal;
use Illuminate\Support\Facades\Http;

class ScannerWorkflowTest extends ScannerFeatureTestCase
{
    public function test_scanner_page_renders_for_user(): void
    {
        [$user] = $this->makeUserAndAccount();
        $this->actingAs($user)->get(route('scanner.index'))->assertOk();
    }

    public function test_scanner_start_stop_toggles_config(): void
    {
        [$user] = $this->makeUserAndAccount();
        $config = $this->makeConfig($user->id);

        $this->actingAs($user)->post(route('scanner.start'))->assertRedirect();
        $this->assertSame('running', $config->fresh()->status);

        $this->actingAs($user)->post(route('scanner.stop'))->assertRedirect();
        $this->assertSame('stopped', $config->fresh()->status);
    }

    public function test_watchlist_add_requires_known_symbol(): void
    {
        [$user] = $this->makeUserAndAccount();
        $config = $this->makeConfig($user->id, ['status' => 'stopped']);
        $this->makeMarket();

        $this->actingAs($user)
            ->post(route('scanner.watchlist.store'), ['symbol' => 'BTCUSDT', 'exchange' => '', 'market_type' => 'both'])
            ->assertRedirect();
        $this->assertDatabaseHas('scanner_watchlist_entries', ['config_id' => $config->id, 'symbol' => 'BTCUSDT']);

        $this->actingAs($user)
            ->post(route('scanner.watchlist.store'), ['symbol' => 'DOESNOTEXIST', 'exchange' => '', 'market_type' => 'both'])
            ->assertRedirect();
        $this->assertDatabaseMissing('scanner_watchlist_entries', ['config_id' => $config->id, 'symbol' => 'DOESNOTEXIST']);
    }

    public function test_manual_trade_blocked_by_kill_switch(): void
    {
        [$user, $account] = $this->makeUserAndAccount();
        $config = $this->makeConfig($user->id, ['status' => 'running']);
        $this->makeMarket();
        $signal = $this->makeSignal($user->id, $config->id, $account->id);
        $this->fakeBalances(1000);

        GlobalSetting::set('scanner_kill_switch', true);

        $this->actingAs($user)
            ->post(route('scanner.signal.trade', $signal))
            ->assertRedirect()
            ->assertSessionHas('danger');

        $this->assertDatabaseMissing('bot_trades', ['signal_id' => $signal->id]);
        $this->assertSame('qualified', $signal->fresh()->status);
    }

    public function test_manual_trade_executes_in_paper_mode(): void
    {
        [$user, $account] = $this->makeUserAndAccount();
        $config = $this->makeConfig($user->id, ['status' => 'stopped', 'paper_mode' => true, 'trading_mode' => 'paper']);
        $this->makeMarket();
        $signal = $this->makeSignal($user->id, $config->id, $account->id);
        $this->fakeBalances(10000);

        $this->actingAs($user)
            ->post(route('scanner.signal.trade', $signal))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('bot_trades', ['signal_id' => $signal->id, 'source' => 'scanner', 'status' => 'simulated']);
        $this->assertSame('executed', $signal->fresh()->status);
    }

    public function test_refresh_endpoint_returns_signal_json(): void
    {
        [$user, $account] = $this->makeUserAndAccount();
        $config = $this->makeConfig($user->id);
        $this->makeSignal($user->id, $config->id, $account->id, ['signal_score' => 91, 'score_quality' => 'exceptional']);

        $this->actingAs($user)
            ->get(route('scanner.refresh'))
            ->assertOk()
            ->assertJsonPath('config.status', 'stopped')
            ->assertJsonCount(1, 'signals');
    }

    public function test_show_signal_returns_analysis_details(): void
    {
        [$user, $account] = $this->makeUserAndAccount();
        $config = $this->makeConfig($user->id);
        $signal = $this->makeSignal($user->id, $config->id, $account->id, [
            'signal_score' => 88, 'score_breakdown' => ['components' => ['trend' => 90, 'ai' => 85]],
            'reasons' => ['favourable EMA alignment'],
        ]);

        $this->actingAs($user)
            ->get(route('scanner.signal.show', $signal))
            ->assertOk()
            ->assertJsonPath('id', $signal->id)
            ->assertJsonFragment(['favourable EMA alignment']);
    }

    public function test_api_status_requires_auth_and_returns_config(): void
    {
        [$user] = $this->makeUserAndAccount();
        $this->makeConfig($user->id, ['status' => 'running']);

        $this->actingAs($user)->getJson('/api/v1/scanner/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'running');

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/scanner/status')->assertUnauthorized();
    }

    public function test_api_trade_blocks_on_risk_failure(): void
    {
        [$user, $account] = $this->makeUserAndAccount();
        $config = $this->makeConfig($user->id, ['status' => 'running']);
        $this->makeMarket();
        $signal = $this->makeSignal($user->id, $config->id, $account->id);
        $this->fakeBalances(1000);
        GlobalSetting::set('global_trading_kill_switch', true);

        $this->actingAs($user);
        $this->postJson('/api/v1/scanner/signals/'.$signal->id.'/trade')
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        $this->assertDatabaseMissing('bot_trades', ['signal_id' => $signal->id]);
    }
}