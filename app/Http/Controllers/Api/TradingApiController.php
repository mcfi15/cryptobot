<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TradingBot;
use App\Models\BotSignal;
use App\Models\BotTrade;
use App\Models\BotPosition;
use App\Models\ExchangeAccount;
use App\Models\ExchangeMarket;
use App\Services\Exchanges\ExchangeFactory;
use App\Services\Trading\SignalEngine;
use App\Services\Risk\SpotRiskEngine;
use App\Services\Risk\FuturesRiskEngine;
use App\Services\Risk\PortfolioRiskEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TradingApiController extends Controller
{
    public function getSignals(Request $request)
    {
        $signals = BotSignal::whereHas('bot', fn($q) => $q->where('user_id', Auth::id()))
            ->when($request->bot_id, fn($q) => $q->where('bot_id', $request->bot_id))
            ->latest()
            ->limit($request->limit ?? 50)
            ->with('bot')
            ->get();

        return response()->json($signals);
    }

    public function getPositions(Request $request)
    {
        $positions = BotPosition::where('user_id', Auth::id())
            ->where('status', 'open')
            ->when($request->exchange, fn($q) => $q->where('exchange', $request->exchange))
            ->get();

        return response()->json($positions);
    }

    public function getTrades(Request $request)
    {
        $trades = BotTrade::where('user_id', Auth::id())
            ->when($request->bot_id, fn($q) => $q->where('bot_id', $request->bot_id))
            ->when($request->exchange, fn($q) => $q->where('exchange', $request->exchange))
            ->when($request->symbol, fn($q) => $q->where('symbol', $request->symbol))
            ->latest()
            ->paginate($request->per_page ?? 20);

        return response()->json($trades);
    }

    public function testExchange(Request $request, string $exchange)
    {
        $account = ExchangeAccount::where('user_id', Auth::id())
            ->where('exchange', $exchange)
            ->firstOrFail();

        $credentials = $account->getDecryptedCredentials();
        $adapter = ExchangeFactory::make($exchange, $credentials);
        $result = $adapter->testConnection();

        $account->status = $result['connection'] ? 'connected' : 'error';
        $account->last_connection_check = now();
        $account->save();

        return response()->json($result);
    }

    public function getBalances(string $exchange)
    {
        $account = ExchangeAccount::where('user_id', Auth::id())
            ->where('exchange', $exchange)
            ->firstOrFail();

        $credentials = $account->getDecryptedCredentials();
        $adapter = ExchangeFactory::make($exchange, $credentials);
        $balances = $adapter->getBalances();

        return response()->json($balances);
    }

    public function getMarkets(Request $request, string $exchange)
    {
        $account = ExchangeAccount::where('user_id', Auth::id())
            ->where('exchange', $exchange)
            ->firstOrFail();

        $credentials = $account->getDecryptedCredentials();
        $adapter = ExchangeFactory::make($exchange, $credentials);
        $markets = $adapter->getMarkets($request->market_type ?? 'spot');

        return response()->json($markets);
    }

    public function getPerformance(Request $request)
    {
        $userId = Auth::id();

        $totalPnl = BotTrade::where('user_id', $userId)->where('status', 'closed')->sum('pnl');
        $totalTrades = BotTrade::where('user_id', $userId)->where('status', 'closed')->count();
        $wins = BotTrade::where('user_id', $userId)->where('status', 'closed')->where('pnl', '>', 0)->count();
        $winRate = $totalTrades > 0 ? round($wins / $totalTrades * 100, 1) : 0;

        $avgWin = BotTrade::where('user_id', $userId)->where('status', 'closed')->where('pnl', '>', 0)->avg('pnl') ?? 0;
        $avgLoss = abs(BotTrade::where('user_id', $userId)->where('status', 'closed')->where('pnl', '<', 0)->avg('pnl') ?? 0);
        $profitFactor = $avgLoss > 0 ? round($avgWin / $avgLoss, 2) : 0;

        return response()->json([
            'total_pnl' => $totalPnl,
            'total_trades' => $totalTrades,
            'win_rate' => $winRate,
            'profit_factor' => $profitFactor,
            'avg_win' => round($avgWin, 2),
            'avg_loss' => round($avgLoss, 2),
        ]);
    }
}
