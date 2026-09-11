<?php

namespace App\Http\Controllers;

use App\Models\TradingBot;
use App\Models\ExchangeAccount;
use App\Models\BotSignal;
use App\Models\BotTrade;
use App\Models\BotPosition;
use App\Services\Trading\SignalEngine;
use App\Services\Risk\SpotRiskEngine;
use App\Services\Risk\FuturesRiskEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BotController extends Controller
{
    public function index()
    {
        $bots = Auth::user()->bots()->with('exchangeAccount')->get();
        return view('bots.index', compact('bots'));
    }

    public function create()
    {
        $exchanges = Auth::user()->exchangeAccounts()->where('status', 'connected')->get();
        return view('bots.create', compact('exchanges'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'exchange_account_id' => 'required|exists:exchange_accounts,id',
            'market_type' => 'required|in:spot,futures',
            'mode' => 'required|in:manual,signal_only,paper,live',
            'strategy' => 'required|string',
            'symbols' => 'required|array',
            'timeframes' => 'required|array',
            'risk_per_trade' => 'nullable|numeric|min:0.1|max:5',
            'max_daily_loss' => 'nullable|numeric|min:0.5|max:10',
            'max_open_positions' => 'nullable|integer|min:1|max:20',
            'max_position_size' => 'nullable|numeric|min:10',
            'max_leverage' => 'nullable|integer|min:1|max:125',
            'ai_threshold' => 'nullable|numeric|min:50|max:100',
            'signal_threshold' => 'nullable|numeric|min:50|max:100',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['status'] = 'stopped';
        $validated['settings'] = json_encode($request->only(['stop_loss_method', 'take_profit_method', 'trailing_stop']));

        $bot = TradingBot::create($validated);

        return redirect()->route('bots.show', $bot)->with('success', 'Bot created.');
    }

    public function show(TradingBot $bot)
    {
        $bot->load(['exchangeAccount', 'positions', 'trades']);
        $recentSignals = $bot->signals()->latest()->limit(10)->get();
        return view('bots.show', compact('bot', 'recentSignals'));
    }

    public function start(TradingBot $bot)
    {
        $bot->status = 'running';
        $bot->save();

        return redirect()->back()->with('success', 'Bot started.');
    }

    public function stop(TradingBot $bot)
    {
        $bot->status = 'stopped';
        $bot->save();

        return redirect()->back()->with('success', 'Bot stopped.');
    }

    public function pause(TradingBot $bot)
    {
        $bot->status = 'paused';
        $bot->save();

        return redirect()->back()->with('success', 'Bot paused.');
    }

    public function signals(TradingBot $bot)
    {
        $signals = $bot->signals()->latest()->paginate(50);
        return view('bots.signals', compact('bot', 'signals'));
    }

    public function trades(TradingBot $bot)
    {
        $trades = $bot->trades()->latest()->paginate(50);
        return view('bots.trades', compact('bot', 'trades'));
    }

    public function destroy(TradingBot $bot)
    {
        $bot->status = 'stopped';
        $bot->save();
        $bot->delete();

        return redirect()->route('bots.index')->with('success', 'Bot deleted.');
    }
}
