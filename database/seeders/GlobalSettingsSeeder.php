<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GlobalSetting;

class GlobalSettingsSeeder extends Seeder
{
    public function run(): void
    {
        GlobalSetting::set('site_name', 'Cryptobot', 'Site name shown in header, title and footer');
        GlobalSetting::set('site_tagline', 'Automated AI Trading', 'Tagline under the brand name');
        GlobalSetting::set('site_description', 'Multi-exchange automated AI crypto trading platform.', 'Short site description');
        GlobalSetting::set('support_email', '', 'Support email shown to users');

        GlobalSetting::set('live_trading_enabled', false, 'Globally enable/disable live trading');
        GlobalSetting::set('emergency_stop', false, 'Emergency stop - kill all trading');
        GlobalSetting::set('max_risk_per_trade', 0.5, 'Maximum risk per trade (percent)');
        GlobalSetting::set('max_daily_loss', 2.0, 'Maximum daily loss (percent)');
        GlobalSetting::set('max_leverage', 20, 'Maximum leverage across all bots');
        GlobalSetting::set('max_open_positions', 10, 'Maximum open positions across all bots');
        GlobalSetting::set('max_connected_exchanges', 10, 'Maximum connected exchanges per user');

        GlobalSetting::set('scanner_kill_switch', false, 'Kill switch for the market scanner auto trades');
        GlobalSetting::set('global_trading_kill_switch', false, 'Emergency switch blocking ALL scanner executions');
        GlobalSetting::set('scanner_live_allowed', false, 'Allow scanner live (non-paper) trading');
        GlobalSetting::set('scanner_default_min_score', 75, 'Default minimum scanner signal score');
        GlobalSetting::set('scanner_default_ai', 70, 'Default minimum AI probability (%)');
        GlobalSetting::set('scanner_default_rr', 1.5, 'Default minimum risk/reward');
        GlobalSetting::set('scanner_default_expiry', 30, 'Default signal expiry in minutes');

        // Portfolio-level capital protection (enforced by scanner risk engine).
        GlobalSetting::set('global_max_drawdown', 15, 'Maximum portfolio drawdown (%) from equity peak before auto-pause');
        GlobalSetting::set('liquidation_distance_min_pct', 3, 'Minimum allowed distance (%) between entry and estimated liquidation price for futures');
        GlobalSetting::set('max_correlated_exposure', 30, 'Maximum combined notional exposure (%) for correlated cluster');
        GlobalSetting::set('correlated_assets', 'BTC,ETH,SOL,BNB,XRP,ADA,DOGE,AVAX,LINK,LTC,DOT,MATIC', 'Base assets treated as a correlated major cluster');
    }
}
