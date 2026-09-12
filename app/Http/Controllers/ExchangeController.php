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

        $checks = [
            'connection' => [
                'label' => 'API connection',
                'pass' => 'Successfully connected to ' . strtoupper($exchange->exchange),
                'fail' => 'Could not connect to ' . strtoupper($exchange->exchange),
            ],
            'account_access' => [
                'label' => 'Account access',
                'pass' => 'API key can read your account',
                'fail' => 'API key does not have account read access',
            ],
            'trading_permission' => [
                'label' => 'Trading permission',
                'pass' => 'API key is allowed to trade',
                'fail' => 'API key does not have trading permission',
            ],
            'market_access' => [
                'label' => 'Market data access',
                'pass' => 'Market data is accessible',
                'fail' => 'Could not access market data',
            ],
        ];

        $rows = [];
        foreach ($checks as $key => $def) {
            $ok = (bool) ($result[$key] ?? false);
            $rows[] = [
                'ok' => $ok,
                'label' => $def['label'],
                'text' => $ok ? $def['pass'] : $def['fail'],
            ];
        }

        return back()->with('test_result', [
            'exchange' => strtoupper($exchange->exchange),
            'label' => $exchange->label,
            'connected' => (bool) $result['connection'],
            'checks' => $rows,
            'error' => $result['error'] ?? null,
        ]);
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
