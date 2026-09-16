<?php

namespace Tests\Feature\Scanner;

use App\Models\ScannerConfig;
use App\Models\User;
use App\Console\Commands\ScannerDebugCommand;
use Illuminate\Support\Facades\Http;

class ScannerDebugCommandTest extends ScannerFeatureTestCase
{
    public function test_debug_errors_on_unknown_user(): void
    {
        $this->artisan('scanner:debug', ['--user' => 999999])
            ->expectsOutputToContain('No users found.')
            ->assertExitCode(1);
    }

    public function test_debug_reports_no_accounts_gracefully(): void
    {
        $user = User::factory()->create();
        $this->makeConfig($user->id, ['min_signal_score' => 75]);

        $this->artisan('scanner:debug', ['--user' => $user->id])
            ->expectsOutputToContain('No connected exchange accounts.')
            ->assertExitCode(0);
    }

    public function test_debug_json_output_is_valid_with_no_accounts(): void
    {
        $user = User::factory()->create();
        $this->makeConfig($user->id);

        $this->artisan('scanner:debug', ['--user' => $user->id, '--json' => 'true'])
            ->assertExitCode(0)
            ->expectsOutputToContain('min_signal_score');
    }

    public function test_debug_handles_empty_exchange_pipeline(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        [$user, $account] = $this->makeUserAndAccount();
        $this->makeConfig($user->id, ['status' => 'running', 'min_signal_score' => 75]);

        $this->artisan('scanner:debug', ['--user' => $user->id])
            ->assertExitCode(0);
    }

    public function test_apply_lowers_high_thresholds_to_defaults(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        [$user, $account] = $this->makeUserAndAccount();
        $config = $this->makeConfig($user->id, [
            'status' => 'running',
            'min_signal_score' => 75,
            'min_ai_probability' => 70.00,
        ]);

        $this->artisan('scanner:debug', ['--user' => $user->id, '--apply' => 'true'])
            ->assertExitCode(0);

        $config->refresh();
        $this->assertSame(55, (int) $config->min_signal_score);
        $this->assertSame(55.0, (float) $config->min_ai_probability);
    }

    public function test_recommendation_returns_defaults_when_no_candidates(): void
    {
        $rec = ScannerDebugCommand::recommendThresholds([]);

        $this->assertSame(55, $rec['suggested_score']);
        $this->assertSame(55.0, $rec['suggested_ai']);
    }

    public function test_recommendation_clamps_suggestions(): void
    {
        $rows = collect(range(1, 40))->map(fn ($i) => [
            'score' => min($i + 30, 100),
            'ai' => min($i + 30, 100.0),
            'rr' => 1.5,
            'gate' => 'score',
        ])->all();

        $rec = ScannerDebugCommand::recommendThresholds($rows);

        // px threshold must stay inside the actionable window.
        $this->assertGreaterThanOrEqual(50, $rec['suggested_score']);
        $this->assertLessThanOrEqual(75, $rec['suggested_score']);
        $this->assertGreaterThanOrEqual(50.0, $rec['suggested_ai']);
        $this->assertLessThanOrEqual(68.0, $rec['suggested_ai']);
    }

    public function test_recommendation_emits_targeted_cut_off(): void
    {
        $rows = [
            ['score' => 40, 'ai' => 45, 'rr' => 1.5, 'gate' => 'neutral'],
            ['score' => 55, 'ai' => 55, 'rr' => 1.5, 'gate' => 'qualified'],
            ['score' => 70, 'ai' => 65, 'rr' => 1.5, 'gate' => 'ai'],
            ['score' => 80, 'ai' => 75, 'rr' => 1.5, 'gate' => 'qualified'],
        ];

        $rec = ScannerDebugCommand::recommendThresholds($rows);

        // p78 score cut falls between 70 and 80 → 73; p65 AI cut → ~65.
        $this->assertSame(73, $rec['suggested_score']);
        $this->assertSame(64.5, $rec['suggested_ai']);
    }
}