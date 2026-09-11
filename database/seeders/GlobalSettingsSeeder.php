<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GlobalSetting;

class GlobalSettingsSeeder extends Seeder
{
    public function run(): void
    {
        GlobalSetting::set('live_trading_enabled', false, 'Globally enable/disable live trading');
        GlobalSetting::set('emergency_stop', false, 'Emergency stop - kill all trading');
        GlobalSetting::set('max_risk_per_trade', 0.5, 'Maximum risk per trade (percent)');
        GlobalSetting::set('max_daily_loss', 2.0, 'Maximum daily loss (percent)');
        GlobalSetting::set('max_leverage', 20, 'Maximum leverage across all bots');
        GlobalSetting::set('max_open_positions', 10, 'Maximum open positions across all bots');
        GlobalSetting::set('max_connected_exchanges', 10, 'Maximum connected exchanges per user');
    }
}
