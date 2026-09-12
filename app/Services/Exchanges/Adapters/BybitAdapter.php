<?php

namespace App\Services\Exchanges\Adapters;

use App\Services\Exchanges\ExchangeCapabilities;

class BybitAdapter extends BaseAdapter
{
    public function getName(): string { return 'bybit'; }

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
            trailingStop: true,
            reduceOnly: true,
            hedgeMode: true,
            oneWayMode: true,
            funding: true,
            websocket: true,
            maxLeverage: 100,
            supportedIntervals: ['1','3','5','15','30','60','240','D'],
        );
    }

    protected function getBaseUrls(): array
    {
        return [
            'rest' => 'https://api.bybit.com',
            'futures' => 'https://api.bybit.com',
        ];
    }

    protected function signRequest(array $params): array
    {
        $apiKey = $this->credentials['api_key'];
        $secret = $this->credentials['api_secret'];

        $timestamp = round(microtime(true) * 1000);
        $recvWindow = '5000';

        $body = $params['body'] ?? [];
        $query = $params['query'] ?? [];

        if (count($body) > 0) {
            $signStr = $timestamp . $apiKey . $recvWindow . json_encode($body);
        } else {
            $params['query'] = $query;
            $signStr = $timestamp . $apiKey . $recvWindow . http_build_query($query);
        }

        $signature = hash_hmac('sha256', $signStr, $secret);

        $params['headers'] = [
            'X-BAPI-API-KEY' => $apiKey,
            'X-BAPI-SIGN' => $signature,
            'X-BAPI-TIMESTAMP' => (string)$timestamp,
            'X-BAPI-RECV-WINDOW' => $recvWindow,
            'Content-Type' => 'application/json',
        ];

        return $params;
    }

    public function testConnection(): array
    {
        try {
            $result = $this->get('/v5/account/wallet-balance', ['query' => ['accountType' => 'UNIFIED']], signed: true);
            return [
                'connection' => true,
                'account_access' => isset($result['result']),
                'trading_permission' => true,
                'market_access' => true,
            ];
        } catch (\Exception $e) {
            return ['connection' => false, 'error' => $e->getMessage()];
        }
    }

    public function getMarkets(string $marketType = 'spot'): array
    {
        $category = $marketType === 'futures' ? 'linear' : 'spot';
        $result = $this->get('/v5/market/instruments-info', ['query' => ['category' => $category]], signed: false);

        $markets = [];
        foreach ($result['result']['list'] ?? [] as $item) {
            $markets[] = [
                'symbol' => $item['symbol'],
                'base_asset' => $item['baseCoin'],
                'quote_asset' => $item['quoteCoin'],
                'price_precision' => (int) ($item['priceScale'] ?? 8),
                'quantity_precision' => (int) ($item['lotScale'] ?? 8),
                'min_quantity' => $item['lotSizeFilter']['minOrderQty'] ?? '0.001',
                'tick_size' => $item['priceFilter']['tickSize'] ?? '0.01',
                'status' => $item['status'] === 'Trading' ? 'trading' : 'halted',
            ];
        }
        return $markets;
    }

    public function getTicker(string $symbol): array
    {
        $result = $this->get('/v5/market/tickers', ['query' => ['category' => 'spot', 'symbol' => $symbol]], signed: false);
        $ticker = $result['result']['list'][0] ?? [];
        return [
            'symbol' => $ticker['symbol'],
            'price' => $ticker['lastPrice'],
            'bid' => $ticker['bid1Price'],
            'ask' => $ticker['ask1Price'],
            'high' => $ticker['highPrice24h'],
            'low' => $ticker['lowPrice24h'],
            'volume' => $ticker['volume24h'],
            'change_24h' => $ticker['price24hPcnt'],
        ];
    }

    public function getOrderBook(string $symbol, int $limit = 20): array
    {
        $result = $this->get('/v5/market/orderbook', ['query' => ['category' => 'spot', 'symbol' => $symbol, 'limit' => $limit]], signed: false);
        return [
            'bids' => $result['result']['b'] ?? [],
            'asks' => $result['result']['a'] ?? [],
        ];
    }

    public function getKlines(string $symbol, string $interval, int $limit = 500): array
    {
        $result = $this->get('/v5/market/kline', ['query' => [
            'category' => 'spot', 'symbol' => $symbol, 'interval' => $interval, 'limit' => $limit,
        ]], signed: false);

        $klines = [];
        foreach ($result['result']['list'] ?? [] as $k) {
            $klines[] = [
                'open_time' => $k[0],
                'open' => $k[1],
                'high' => $k[2],
                'low' => $k[3],
                'close' => $k[4],
                'volume' => $k[5],
            ];
        }
        return $klines;
    }

    public function getBalances(): array
    {
        $result = $this->get('/v5/account/wallet-balance', ['query' => ['accountType' => 'UNIFIED']], signed: true);
        $balances = [];
        foreach ($result['result']['list'][0]['coin'] ?? [] as $c) {
            if ((float)$c['walletBalance'] > 0) {
                $balances[] = [
                    'asset' => $c['coin'],
                    'free' => $c['availableToWithdraw'],
                    'locked' => $c['walletBalance'] - $c['availableToWithdraw'],
                    'total' => $c['walletBalance'],
                ];
            }
        }
        return $balances;
    }

    public function getOpenOrders(?string $symbol = null): array
    {
        $params = ['body' => ['category' => 'spot']];
        if ($symbol) $params['body']['symbol'] = $symbol;

        $result = $this->post('/v5/order/realtime', $params);
        return array_map(fn($o) => [
            'order_id' => $o['orderId'],
            'symbol' => $o['symbol'],
            'side' => $o['side'],
            'type' => $o['orderType'],
            'price' => $o['price'],
            'quantity' => $o['qty'],
            'filled' => $o['cumExecQty'],
            'status' => $o['orderStatus'],
        ], $result['result']['list'] ?? []);
    }

    public function getOrder(string $orderId): array
    {
        $result = $this->post('/v5/order/history', ['body' => ['category' => 'spot', 'orderId' => $orderId]]);
        $order = $result['result']['list'][0] ?? [];
        return [
            'order_id' => $order['orderId'] ?? $orderId,
            'symbol' => $order['symbol'] ?? '',
            'side' => $order['side'] ?? '',
            'type' => $order['orderType'] ?? '',
            'price' => $order['price'] ?? '',
            'quantity' => $order['qty'] ?? '',
            'filled' => $order['cumExecQty'] ?? '',
            'status' => $order['orderStatus'] ?? '',
        ];
    }

    public function placeOrder(array $params): array
    {
        $orderParams = [
            'body' => [
                'category' => $params['market_type'] === 'futures' ? 'linear' : 'spot',
                'symbol' => $params['symbol'],
                'side' => $params['side'],
                'orderType' => $params['type'] ?? 'Market',
                'qty' => $params['quantity'],
            ],
        ];

        if (isset($params['price']) && ($params['type'] ?? '') === 'Limit') {
            $orderParams['body']['price'] = $params['price'];
        }
        if (isset($params['stopLoss'])) {
            $orderParams['body']['stopLoss'] = $params['stopLoss'];
        }
        if (isset($params['takeProfit'])) {
            $orderParams['body']['takeProfit'] = $params['takeProfit'];
        }
        if (isset($params['reduceOnly'])) {
            $orderParams['body']['reduceOnly'] = $params['reduceOnly'];
        }

        $result = $this->post('/v5/order/create', $orderParams);
        return [
            'order_id' => $result['result']['orderId'] ?? '',
            'symbol' => $params['symbol'],
            'status' => 'NEW',
        ];
    }

    public function cancelOrder(string $orderId, ?string $symbol = null): array
    {
        $params = ['body' => ['category' => 'spot', 'orderId' => $orderId]];
        if ($symbol) $params['body']['symbol'] = $symbol;

        $result = $this->post('/v5/order/cancel', $params);
        return ['order_id' => $orderId, 'status' => 'CANCELLED'];
    }

    public function getPositions(): array
    {
        $result = $this->post('/v5/position/list', ['body' => ['category' => 'linear']]);
        return array_map(fn($p) => [
            'symbol' => $p['symbol'],
            'side' => $p['side'],
            'size' => $p['size'],
            'entry_price' => $p['avgPrice'],
            'mark_price' => $p['markPrice'],
            'unrealized_pnl' => $p['unrealisedPnl'],
            'leverage' => $p['leverage'],
            'margin' => $p['positionMargin'],
            'liquidation_price' => $p['liqPrice'],
        ], $result['result']['list'] ?? []);
    }

    public function getTradeHistory(?string $symbol = null, int $limit = 100): array
    {
        $params = ['body' => ['category' => 'spot', 'limit' => $limit]];
        if ($symbol) $params['body']['symbol'] = $symbol;

        $result = $this->post('/v5/execution/list', $params);
        return array_map(fn($t) => [
            'trade_id' => $t['execId'],
            'symbol' => $t['symbol'],
            'side' => $t['side'],
            'price' => $t['execPrice'],
            'quantity' => $t['execQty'],
            'fee' => $t['execFee'],
            'time' => $t['execTime'],
        ], $result['result']['list'] ?? []);
    }

    public function getFundingRate(?string $symbol = null): array
    {
        $params = ['query' => ['category' => 'linear']];
        if ($symbol) $params['query']['symbol'] = $symbol;

        $result = $this->get('/v5/market/funding/history', $params, signed: false);
        return $result['result']['list'] ?? [];
    }

    public function setLeverage(string $symbol, int $leverage): array
    {
        $result = $this->post('/v5/position/set-leverage', ['body' => [
            'category' => 'linear',
            'symbol' => $symbol,
            'buyLeverage' => (string)$leverage,
            'sellLeverage' => (string)$leverage,
        ]]);
        return ['leverage' => $leverage, 'status' => 'ok'];
    }

    public function setPositionMode(string $mode): array
    {
        $result = $this->post('/v5/position/switch-mode', ['body' => [
            'category' => 'linear',
            'coin' => 'USDT',
            'mode' => $mode === 'hedge' ? 3 : 0,
        ]]);
        return ['mode' => $mode, 'status' => 'ok'];
    }
}
