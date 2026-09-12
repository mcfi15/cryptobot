<?php

namespace App\Services\Scanner;

use App\Models\ExchangeAccount;
use App\Models\ExchangeMarket;
use App\Services\Exchanges\ExchangeFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MarketDiscoveryService
{
    public function connectedAccounts(int $userId, ?array $exchanges = null): \Illuminate\Support\Collection
    {
        return ExchangeAccount::where('user_id', $userId)
            ->where('status', 'connected')
            ->when($exchanges, fn ($q) => $q->whereIn('exchange', $exchanges))
            ->get();
    }

    /**
     * Discover markets for an exchange account and market type.
     * Uses the exchange's own instrument info (not a hard-coded list).
     *
     * @return array<int, array>
     */
    public function markets(ExchangeAccount $account, string $marketType): array
    {
        $cacheKey = "scanner.markets.{$account->id}.{$marketType}";

        return Cache::remember($cacheKey, 3600, function () use ($account, $marketType) {
            try {
                $adapter = ExchangeFactory::make($account->exchange, $account->getDecryptedCredentials());
                $markets = $adapter->getMarkets($marketType);
            } catch (\Throwable $e) {
                Log::warning("scanner.discovery.failed", [
                    'exchange' => $account->exchange,
                    'market_type' => $marketType,
                    'error' => $e->getMessage(),
                ]);
                return [];
            }

            return array_values(array_map(fn (array $m) => $this->normalizeMarket($account, $marketType, $m), $markets));
        });
    }

    protected function normalizeMarket(ExchangeAccount $account, string $marketType, array $m): array
    {
        $symbol = $m['symbol'] ?? '';
        $base = $m['base_asset'] ?? null;
        $quote = $m['quote_asset'] ?? null;

        if (!$base || !$quote) {
            $parts = $this->splitSymbol($symbol);
            $base = $base ?? $parts['base'];
            $quote = $quote ?? $parts['quote'];
        }

        $market = [
            'exchange' => $account->exchange,
            'symbol' => $symbol,
            'base_asset' => $base,
            'quote_asset' => $quote,
            'market_type' => $marketType,
            'price_precision' => (int) ($m['price_precision'] ?? 8),
            'quantity_precision' => (int) ($m['quantity_precision'] ?? 8),
            'min_quantity' => $m['min_quantity'] ?? '0.00001',
            'tick_size' => $m['tick_size'] ?? '0.00000001',
            'min_notional' => $m['min_notional'] ?? null,
            'status' => $m['status'] ?? 'trading',
        ];

        try {
            ExchangeMarket::updateOrCreate(
                [
                    'exchange' => $account->exchange,
                    'symbol' => $symbol,
                    'market_type' => $marketType,
                ],
                [
                    'base_asset' => $base,
                    'quote_asset' => $quote,
                    'price_precision' => $market['price_precision'],
                    'quantity_precision' => $market['quantity_precision'],
                    'min_quantity' => $market['min_quantity'],
                    'tick_size' => $market['tick_size'],
                    'min_notional' => $market['min_notional'],
                    'status' => $market['status'],
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('scanner.market.upsert.failed', ['symbol' => $symbol, 'error' => $e->getMessage()]);
        }

        return $market;
    }

    protected function splitSymbol(string $symbol): array
    {
        $quotes = ['USDT', 'USDC', 'BUSD', 'FDUSD', 'TUSD', 'DAI', 'BTC', 'ETH', 'EUR', 'USD'];
        foreach ($quotes as $quote) {
            if (str_ends_with($symbol, $quote) && strlen($symbol) > strlen($quote)) {
                return [
                    'base' => substr($symbol, 0, -strlen($quote)),
                    'quote' => $quote,
                ];
            }
        }
        return ['base' => $symbol, 'quote' => ''];
    }
}