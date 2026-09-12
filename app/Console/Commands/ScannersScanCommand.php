<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Scanner\ScannerService;
use Illuminate\Console\Command;

class ScannersScanCommand extends Command
{
    protected $signature = 'scanner:run {--user= : Scan for a specific user id (defaults to all)}';
    protected $description = 'Run the market scanner for all (or one) users';

    public function handle(ScannerService $scanner): int
    {
        $userId = $this->option('user');
        $users = $userId ? collect([User::find((int) $userId)])->filter() : User::all();

        if ($users->isEmpty()) {
            $this->error('No users found to scan for.');
            return 1;
        }

        foreach ($users as $user) {
            $config = \App\Models\ScannerConfig::forUser($user->id);
            if ($config->status !== 'running') {
                $this->line("Skip {$user->email}: scanner stopped.");
                continue;
            }

            $this->info("Scanning markets for {$user->email}...");
            $result = $scanner->run($user);

            $this->line($result['message'] ?? 'No result');
            if (($result['ok'] ?? false) === false) {
                $this->warn('  '.($result['message'] ?? ''));
            }
        }

        return 0;
    }
}