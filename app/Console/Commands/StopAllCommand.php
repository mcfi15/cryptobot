<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TradingBot;

class StopAllCommand extends Command
{
    protected $signature = 'trading:stop-all';
    protected $description = 'Emergency stop all running bots';

    public function handle()
    {
        $count = TradingBot::where('status', 'running')->update(['status' => 'stopped']);
        $this->info("Stopped {$count} bots.");

        config(['trading.live_enabled' => false]);
        $this->warn('Live trading DISABLED globally.');

        return 0;
    }
}
