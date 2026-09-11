<?php

namespace App\Http\Controllers;

use App\Models\TradingBot;
use App\Models\BotSignal;
use App\Models\BotTrade;
use App\Models\BotPosition;
use App\Models\ExchangeAccount;
use App\Services\Exchanges\ExchangeFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
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

        return view('dashboard.index', compact(
            'exchanges', 'bots', 'activeBots', 'positions',
            'totalPnl', 'todayPnl', 'winRate', 'totalTrades',
            'exchangeData'
        ));
    }
}
