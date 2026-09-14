<?php

namespace App\Http\Controllers;

use App\Models\TradingBot;
use App\Models\BotSignal;
use App\Models\BotTrade;
use App\Models\BotPosition;
use App\Models\ExchangeAccount;
use App\Models\ScannerConfig;
use App\Models\ScannerSignal;
use App\Services\Exchanges\ExchangeFactory;
use App\Services\Scanner\ActivityLogger;
use App\Services\Scanner\PerformanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(PerformanceService $performance)
    {
        $user = Auth::user();
        $exchanges = $user->exchangeAccounts;
        $bots = $user->bots()->with('exchangeAccount')->get();
        $activeBots = $bots->where('status', 'running');
        $positions = BotPosition::where('user_id', $user->id)->where('status', 'open')->get();

        $totalPnl = BotTrade::where('user_id', $user->id)->where('status', 'closed')->sum('pnl');
        $todayPnl = BotTrade::where('user_id', $user->id)->where('status', 'closed')
            ->whereDate('closed_at', today())->sum('pnl');

        $totalTrades = BotTrade::where('user_id', $user->id)->where('status', 'closed')->count();
        $winningTrades = BotTrade::where('user_id', $user->id)->where('status', 'closed')->where('pnl', '>', 0)->count();
        $winRate = $totalTrades > 0 ? round($winningTrades / $totalTrades * 100, 1) : 0;

        $exchangeData = [];
        foreach ($exchanges as $ex) {
            $exchangeData[] = [
                'exchange' => $ex->exchange,
                'label' => $ex->label,
                'status' => $ex->status,
                'trading_enabled' => $ex->trading_enabled,
                'bots' => $bots->where('exchange_account_id', $ex->id)->count(),
            ];
        }

        $performanceSummary = $performance->summary($user->id, 90);

        $scannerConfig = ScannerConfig::forUser($user->id);
        $scannerStats = [
            'status' => $scannerConfig->status,
            'paused_reason' => $scannerConfig->paused_reason,
            'auto_trading' => (bool) $scannerConfig->auto_trading,
            'trading_mode' => $scannerConfig->trading_mode,
            'last_scan_at' => $scannerConfig->last_scan_at,
            'qualified' => ScannerSignal::where('user_id', $user->id)->where('status', 'qualified')->count(),
            'watching' => ScannerSignal::where('user_id', $user->id)->where('status', 'watching')->count(),
            'executed' => ScannerSignal::where('user_id', $user->id)->where('status', 'executed')->count(),
        ];

        $opportunities = ScannerSignal::where('user_id', $user->id)
            ->whereIn('status', ['qualified', 'watching'])
            ->orderByDesc('signal_score')
            ->limit(6)
            ->get();

        $activity = ActivityLogger::recent($user->id, 12);

        return view('dashboard.index', compact(
            'exchanges', 'bots', 'activeBots', 'positions',
            'totalPnl', 'todayPnl', 'winRate', 'totalTrades',
            'exchangeData', 'performanceSummary', 'scannerStats',
            'opportunities', 'activity'
        ));
    }
}
