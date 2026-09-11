<?php

namespace App\Services\Exchanges\Adapters;

use App\Services\Exchanges\ExchangeCapabilities;

class MexcAdapter extends BaseAdapter
{
    public function getName(): string { return 'mexc'; }

    public function getCapabilities(): ExchangeCapabilities
    {
        return new ExchangeCapabilities(
            spot: true,
            futures: true,
            margin: true,
            short: true,
            leverage: true,
            stopLoss: true,
            takeProfit: true,
            trailingStop: false,
            reduceOnly: true,
            hedgeMode: false,
            oneWayMode: true,
            funding: true,
            websocket: true,
            maxLeverage: 125,
            supportedIntervals: ['1m','5m','15m','30m','1h','4h','1d'],
        );
    }

    protected function getBaseUrls(): array
    {
        return [
            'rest' => 'https://api.mexc.com',
            'futures' => 'https://futures.mexc.com',
        ];
    }

    protected function signRequest(array $params): array
    {
        $apiKey = $this->credentials['api_key'];
        $secret = $this->credentials['api_secret'];

        $timestamp = round(microtime(true) * 1000);

        $params['body']['timestamp'] = $timestamp;
        $query = http_build_query($params['body'] ?? $params['query'] ?? []);
        $signature = hash_hmac('sha256', $query, $secret);

        $params['headers'] = [
            'X-MEXC-APIKEY' => $apiKey,
        ];

        if (isset($params['body'])) {
            $params['body']['signature'] = $signature;
        } else {
            $params['query']['signature'] = $signature;
        }

        return $params;
    }

    public function testConnection(): array
    {
        try {
            $result = $this->get('/api/v3/account', [], signed: true);
            return [
                'connection' => true,
                'account_access' => isset($result['balances']),
                'trading_permission' => true,
                'market_access' => true,
            ];
        } catch (\Exception $e) {
            return [
                'connection' => false,
                'account_access' => false,
                'trading_permission' => false,
                'market_access' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getMarkets(string $marketType = 'spot'): array
    {
        $result = $this->get('/api/v3/exchangeInfo');
        $markets = [];

        foreach ($result['symbols'] ?? [] as $symbol) {
            if ($marketType === 'futures') continue;
            $markets[] = [
                'symbol' => $symbol['symbol'],
                'base_asset' => $symbol['baseAsset'],
                'quote_asset' => $symbol['quoteAsset'],
                'price_precision' => (int) ($symbol['baseAssetPrecision'] ?? 8),
                'quantity_precision' => (int) ($symbol['baseAssetPrecision'] ?? 8),
                'min_quantity' => $symbol['baseSizePrecision'] ?? '0.00001',
                'tick_size' => $symbol['quotePrecision'] ?? '0.00000001',
                'status' => $symbol['status'] === '1' ? 'trading' : 'halted',
            ];
        }

        return $markets;
    }

    public function getTicker(string $symbol): array
    {
        $result = $this->get('/api/v3/ticker/24hr', ['query' => ['symbol' => $symbol]]);
        return [
            'symbol' => $result['symbol'],
            'price' => $result['lastPrice'],
            'bid' => $result['bidPrice'],
            'ask' => $result['askPrice'],
            'high' => $result['highPrice'],
            'low' => $result['lowPrice'],
            'volume' => $result['volume'],
            'change_24h' => $result['priceChangePercent'],
        ];
    }

    public function getOrderBook(string $symbol, int $limit = 20): array
    {
        $result = $this->get('/api/v3/depth', ['query' => ['symbol' => $symbol, 'limit' => $limit]]);
        return [
            'bids' => $result['bids'] ?? [],
            'asks' => $result['asks'] ?? [],
        ];
    }

    public function getKlines(string $symbol, string $interval, int $limit = 500): array
    {
        $result = $this->get('/api/v3/klines', ['query' => [
            'symbol' => $symbol,
            'interval' => $interval,
            'limit' => $limit,
        ]]);

        $klines = [];
        foreach ($result as $k) {
            $klines[] = [
                'open_time' => $k[0],
                'open' => $k[1],
                'high' => $k[2],
                'low' => $k[3],
                'close' => $k[4],
                'volume' => $k[5],
                'close_time' => $k[6],
            ];
        }
        return $klines;
    }

    public function getBalances(): array
    {
        $result = $this->get('/api/v3/account', [], signed: true);
        $balances = [];
        foreach ($result['balances'] ?? [] as $b) {
            if ((float)$b['free'] > 0 || (float)$b['locked'] > 0) {
                $balances[] = [
                    'asset' => $b['asset'],
                    'free' => $b['free'],
                    'locked' => $b['locked'],
                    'total' => bcadd($b['free'], $b['locked']),
                ];
            }
        }
        return $balances;
    }

    public function getOpenOrders(?string $symbol = null): array
    {
        $params = ['query' => []];
        if ($symbol) $params['query']['symbol'] = $symbol;

        $result = $this->get('/api/v3/openOrders', $params, signed: true);

        return array_map(fn($o) => [
            'order_id' => $o['orderId'],
            'symbol' => $o['symbol'],
            'side' => $o['side'],
            'type' => $o['type'],
            'price' => $o['price'],
            'quantity' => $o['origQty'],
            'filled' => $o['executedQty'],
            'status' => $o['status'],
            'time' => $o['time'],
        ], $result);
    }

    public function getOrder(string $orderId): array
    {
        $result = $this->get('/api/v3/order', ['query' => ['orderId' => $orderId]], signed: true);
        return [
            'order_id' => $result['orderId'],
            'symbol' => $result['symbol'],
            'side' => $result['side'],
            'type' => $result['type'],
            'price' => $result['price'],
            'quantity' => $result['origQty'],
            'filled' => $result['executedQty'],
            'status' => $result['status'],
            'time' => $result['time'],
        ];
    }

    public function placeOrder(array $params): array
    {
        $orderParams = [
            'body' => [
                'symbol' => $params['symbol'],
                'side' => $params['side'],
                'type' => $params['type'] ?? 'MARKET',
            ],
        ];

        if (isset($params['quantity'])) {
            $orderParams['body']['quantity'] = $params['quantity'];
        }
        if (isset($params['price'])) {
            $orderParams['body']['price'] = $params['price'];
        }
        if (isset($params['timeInForce'])) {
            $orderParams['body']['timeInForce'] = $params['timeInForce'];
        }

        $result = $this->post('/api/v3/order', $orderParams);

        return [
            'order_id' => $result['orderId'],
            'symbol' => $result['symbol'],
            'status' => $result['status'],
            'side' => $result['side'],
        ];
    }

    public function cancelOrder(string $orderId, ?string $symbol = null): array
    {
        $params = ['body' => ['orderId' => $orderId]];
        if ($symbol) $params['body']['symbol'] = $symbol;

        $result = $this->delete('/api/v3/order', $params);
        return ['order_id' => $result['orderId'], 'status' => $result['status']];
    }

    public function getPositions(): array { return []; }
    public function getTradeHistory(?string $symbol = null, int $limit = 100): array { return []; }
    public function getFundingRate(?string $symbol = null): array { return []; }
    public function setLeverage(string $symbol, int $leverage): array { return []; }
    public function setPositionMode(string $mode): array { return []; }
}
