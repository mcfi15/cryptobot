<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TradingBot;
use App\Models\ExchangeAccount;
use App\Models\ExchangeMarket;
use App\Models\MarketCandle;
use App\Services\Exchanges\ExchangeFactory;

class TradingHealthCommand extends Command
{
    protected $signature = 'trading:health';
    protected $description = 'Check trading system health';

    public function handle()
    {
        $this->info('=== Trading System Health Check ===');

        $exchanges = ExchangeAccount::all();
        $this->info("Connected exchanges: {$exchanges->count()}");

        foreach ($exchanges as $ex) {
            $this->line("  {$ex->exchange} ({$ex->label}): {$ex->status}");
        }

        $bots = TradingBot::all();
        $running = $bots->where('status', 'running')->count();
        $this->info("Bots: {$bots->count()} total, {$running} running");

        $this->info("LIVE_TRADING: " . (config('trading.live_enabled', false) ? 'ENABLED' : 'DISABLED'));

        $this->info('=== Health Check Complete ===');
        return 0;
    }
}
