<?php

namespace App\Services\Exchanges\Adapters;

use App\Services\Exchanges\ExchangeCapabilities;

class BinanceAdapter extends BaseAdapter
{
    public function getName(): string { return 'binance'; }

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
            maxLeverage: 125,
            supportedIntervals: ['1m','3m','5m','15m','30m','1h','2h','4h','6h','8h','12h','1d','3d','1w','1M'],
        );
    }

    protected function getBaseUrls(): array
    {
        return [
            'rest' => 'https://api.binance.com',
            'futures' => 'https://fapi.binance.com',
        ];
    }

    protected function signRequest(array $params): array
    {
        $apiKey = $this->credentials['api_key'];
        $secret = $this->credentials['api_secret'];

        if (isset($params['body'])) {
            $data = &$params['body'];
        } else {
            $params['query'] = $params['query'] ?? [];
            $data = &$params['query'];
        }

        $data['timestamp'] = round(microtime(true) * 1000);
        $data['signature'] = hash_hmac('sha256', http_build_query($data), $secret);

        $params['headers'] = ['X-MBX-APIKEY' => $apiKey];

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
            return ['connection' => false, 'error' => $e->getMessage()];
        }
    }

    public function getMarkets(string $marketType = 'spot'): array
    {
        $result = $this->get('/api/v3/exchangeInfo');
        $markets = [];
        foreach ($result['symbols'] ?? [] as $s) {
            $markets[] = [
                'symbol' => $s['symbol'],
                'base_asset' => $s['baseAsset'],
                'quote_asset' => $s['quoteAsset'],
                'price_precision' => (int) ($s['baseAssetPrecision'] ?? 8),
                'quantity_precision' => (int) ($s['baseAssetPrecision'] ?? 8),
                'min_quantity' => $s['filters'][1]['minQty'] ?? '0.001',
                'tick_size' => $s['filters'][0]['tickSize'] ?? '0.01',
                'status' => $s['status'] === 'TRADING' ? 'trading' : 'halted',
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
        return ['bids' => $result['bids'] ?? [], 'asks' => $result['asks'] ?? []];
    }

    public function getKlines(string $symbol, string $interval, int $limit = 500): array
    {
        $result = $this->get('/api/v3/klines', ['query' => ['symbol' => $symbol, 'interval' => $interval, 'limit' => $limit]]);
        $klines = [];
        foreach ($result as $k) {
            $klines[] = [
                'open_time' => $k[0], 'open' => $k[1], 'high' => $k[2],
                'low' => $k[3], 'close' => $k[4], 'volume' => $k[5], 'close_time' => $k[6],
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
                    'asset' => $b['asset'], 'free' => $b['free'],
                    'locked' => $b['locked'], 'total' => bcadd($b['free'], $b['locked']),
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
            'order_id' => $o['orderId'], 'symbol' => $o['symbol'],
            'side' => $o['side'], 'type' => $o['type'],
            'price' => $o['price'], 'quantity' => $o['origQty'],
            'filled' => $o['executedQty'], 'status' => $o['status'],
        ], $result);
    }

    public function getOrder(string $orderId): array
    {
        $result = $this->get('/api/v3/order', ['query' => ['orderId' => $orderId]], signed: true);
        return [
            'order_id' => $result['orderId'], 'symbol' => $result['symbol'],
            'side' => $result['side'], 'type' => $result['type'],
            'price' => $result['price'], 'quantity' => $result['origQty'],
            'filled' => $result['executedQty'], 'status' => $result['status'],
        ];
    }

    public function placeOrder(array $params): array
    {
        $orderParams = ['body' => [
            'symbol' => $params['symbol'],
            'side' => $params['side'],
            'type' => $params['type'] ?? 'MARKET',
        ]];

        if (isset($params['quantity'])) $orderParams['body']['quantity'] = $params['quantity'];
        if (isset($params['price'])) $orderParams['body']['price'] = $params['price'];
        if (isset($params['timeInForce'])) $orderParams['body']['timeInForce'] = $params['timeInForce'];

        $result = $this->post('/api/v3/order', $orderParams);
        return [
            'order_id' => $result['orderId'],
            'symbol' => $result['symbol'],
            'status' => $result['status'],
        ];
    }

    public function cancelOrder(string $orderId, ?string $symbol = null): array
    {
        $params = ['query' => ['orderId' => $orderId]];
        if ($symbol) $params['query']['symbol'] = $symbol;
        $result = $this->delete('/api/v3/order', $params);
        return ['order_id' => $result['orderId'], 'status' => $result['status']];
    }

    public function getPositions(): array
    {
        $result = $this->get('/fapi/v2/positionRisk', [], signed: true);
        return array_map(fn($p) => [
            'symbol' => $p['symbol'], 'side' => $p['positionSide'],
            'size' => $p['positionAmt'], 'entry_price' => $p['entryPrice'],
            'mark_price' => $p['markPrice'], 'unrealized_pnl' => $p['unRealizedProfit'],
            'leverage' => $p['leverage'], 'margin' => $p['isolatedMargin'],
            'liquidation_price' => $p['liquidationPrice'],
        ], $result);
    }

    public function getTradeHistory(?string $symbol = null, int $limit = 100): array
    {
        $params = ['query' => ['limit' => $limit]];
        if ($symbol) $params['query']['symbol'] = $symbol;
        $result = $this->get('/api/v3/myTrades', $params, signed: true);
        return array_map(fn($t) => [
            'trade_id' => $t['id'], 'symbol' => $t['symbol'],
            'side' => $t['isBuyer'] ? 'BUY' : 'SELL',
            'price' => $t['price'], 'quantity' => $t['qty'],
            'fee' => $t['commission'], 'time' => $t['time'],
        ], $result);
    }

    public function getFundingRate(?string $symbol = null): array
    {
        $params = ['query' => ['limit' => 10]];
        if ($symbol) $params['query']['symbol'] = $symbol;
        $result = $this->get('/fapi/v1/fundingRate', $params, signed: false);
        return $result;
    }

    public function setLeverage(string $symbol, int $leverage): array
    {
        $result = $this->post('/fapi/v1/leverage', ['body' => ['symbol' => $symbol, 'leverage' => $leverage]]);
        return ['leverage' => $leverage, 'status' => 'ok'];
    }

    public function setPositionMode(string $mode): array
    {
        $result = $this->post('/fapi/v1/positionSide/dual', ['body' => ['dualSidePosition' => $mode === 'hedge']]);
        return ['mode' => $mode, 'status' => 'ok'];
    }
}
