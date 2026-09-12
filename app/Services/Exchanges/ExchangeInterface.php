<?php

namespace App\Services\Exchanges;

use App\Services\Exchanges\ExchangeCapabilities;

interface ExchangeInterface
{
    public function getName(): string;

    public function getCapabilities(): ExchangeCapabilities;

    public function testConnection(): array;

    public function getMarkets(string $marketType = 'spot'): array;

    public function getTicker(string $symbol): array;

    public function getTickers(string $marketType = 'spot'): array;

    public function getOrderBook(string $symbol, int $limit = 20): array;

    public function getKlines(string $symbol, string $interval, int $limit = 500): array;

    public function getBalances(): array;

    public function getOpenOrders(?string $symbol = null): array;

    public function getOrder(string $orderId): array;

    public function placeOrder(array $params): array;

    public function cancelOrder(string $orderId, ?string $symbol = null): array;

    public function getPositions(): array;

    public function getTradeHistory(?string $symbol = null, int $limit = 100): array;

    public function getFundingRate(?string $symbol = null): array;

    public function setLeverage(string $symbol, int $leverage): array;

    public function setPositionMode(string $mode): array;
}
