<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BotSignal;
use App\Models\BotTrade;
use App\Models\ExchangeAccount;
use App\Models\ScannerConfig;
use App\Models\ScannerSignal;
use App\Models\TradingBot;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'users' => User::count(),
            'exchanges' => ExchangeAccount::count(),
            'bots' => TradingBot::count(),
            'running_bots' => TradingBot::where('status', 'running')->count(),
            'signals' => BotSignal::count(),
            'trades' => BotTrade::count(),
            'open_positions' => BotTrade::where('status', 'open')->count(),
            'pnl' => BotTrade::where('status', 'closed')->sum('pnl'),
        ];

        $totalBots = max($stats['bots'], 1);
        $stats['bot_uptime'] = round(($stats['running_bots'] / $totalBots) * 100);

        $scannerStats = [
            'configs' => ScannerConfig::count(),
            'running' => ScannerConfig::where('status', 'running')->count(),
            'signals' => ScannerSignal::count(),
            'executed' => ScannerSignal::where('status', 'executed')->count(),
            'auto_trading' => ScannerConfig::where('auto_trading', true)->count(),
        ];
        $scannerStats['avg_scan_ms'] = (int) ScannerConfig::whereNotNull('last_scan_duration_ms')->avg('last_scan_duration_ms');

        $recentUsers = User::latest()->limit(6)->get();
        $recentSignals = BotSignal::with('bot')->latest()->limit(8)->get();
        $recentTrades = BotTrade::with('bot')->latest()->limit(8)->get();
        $recentScannerSignals = ScannerSignal::with('user')->latest()->limit(8)->get();

        return view('admin.dashboard', compact(
            'stats', 'scannerStats', 'recentUsers', 'recentSignals', 'recentTrades', 'recentScannerSignals'
        ));
    }
}