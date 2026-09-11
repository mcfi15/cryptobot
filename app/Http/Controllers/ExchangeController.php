<?php

namespace App\Http\Controllers;

use App\Models\ExchangeAccount;
use App\Services\Exchanges\ExchangeFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExchangeController extends Controller
{
    public function index()
    {
        $exchanges = Auth::user()->exchangeAccounts;
        return view('exchanges.index', compact('exchanges'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'exchange' => 'required|in:mexc,bybit,binance',
            'label' => 'required|string|max:255',
            'api_key' => 'required|string',
            'api_secret' => 'required|string',
        ]);

        $account = new ExchangeAccount();
        $account->user_id = Auth::id();
        $account->exchange = $validated['exchange'];
        $account->label = $validated['label'];
        $account->setCredentials([
            'api_key' => $validated['api_key'],
            'api_secret' => $validated['api_secret'],
        ]);
        $account->status = 'disconnected';
        $account->save();

        return redirect()->route('exchanges.index')->with('success', 'Exchange account added.');
    }

    public function test(ExchangeAccount $exchange)
    {
        $credentials = $exchange->getDecryptedCredentials();
        $adapter = ExchangeFactory::make($exchange->exchange, $credentials);
        $result = $adapter->testConnection();

        $exchange->status = $result['connection'] ? 'connected' : 'error';
        $exchange->last_connection_check = now();
        $exchange->save();

        return response()->json($result);
    }

    public function toggleTrading(ExchangeAccount $exchange)
    {
        $exchange->trading_enabled = !$exchange->trading_enabled;
        $exchange->save();

        return redirect()->route('exchanges.index')->with('success', 'Trading ' . ($exchange->trading_enabled ? 'enabled' : 'disabled') . '.');
    }

    public function destroy(ExchangeAccount $exchange)
    {
        $exchange->delete();
        return redirect()->route('exchanges.index')->with('success', 'Exchange removed.');
    }
}
