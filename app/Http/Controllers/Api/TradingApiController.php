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

    public function getPerformance(Request $request, \App\Services\Scanner\PerformanceService $performance)
    {
        return response()->json($performance->summary(Auth::id(), (int) $request->get('days', 90)));
    }
}
