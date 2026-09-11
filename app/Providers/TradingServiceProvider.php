<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use App\Models\ExchangeAccount;
use App\Models\TradingBot;
use App\Services\Exchanges\ExchangeFactory;
use App\Services\Exchanges\Adapters\MexcAdapter;
use App\Services\Exchanges\Adapters\BybitAdapter;
use App\Services\Exchanges\Adapters\BinanceAdapter;

class TradingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ExchangeFactory::class, function () {
            return new ExchangeFactory();
        });
    }

    public function boot(): void
    {
        config([
            'trading' => [
                'live_enabled' => env('LIVE_TRADING', false),
                'paper_trading' => env('PAPER_TRADING', true),
                'python_engine_url' => env('PYTHON_ENGINE_URL', 'http://127.0.0.1:8000'),
                'python_engine_key' => env('PYTHON_ENGINE_KEY', ''),
                'supported_exchanges' => ['mexc', 'bybit', 'binance'],
                'default_risk_per_trade' => 0.5,
                'default_max_daily_loss' => 2.0,
                'default_max_open_positions' => 10,
                'default_max_leverage' => 1,
            ],
        ]);

        View::composer(['layouts.navigation', 'layouts.ticker'], function ($view) {
            if (Auth::check()) {
                $userId = Auth::id();
                $view->with('navExchanges', ExchangeAccount::where('user_id', $userId)->get());
                $view->with('navBots', TradingBot::where('user_id', $userId)->get());
            } else {
                $view->with('navExchanges', collect());
                $view->with('navBots', collect());
            }
            $view->with('navLiveTrading', (bool) config('trading.live_enabled', false));
        });
    }
}
