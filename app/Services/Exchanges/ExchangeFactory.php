<?php

namespace App\Services\Exchanges;

use App\Services\Exchanges\Adapters\MexcAdapter;
use App\Services\Exchanges\Adapters\BybitAdapter;
use App\Services\Exchanges\Adapters\BinanceAdapter;

class ExchangeFactory
{
    protected static array $adapters = [
        'mexc' => MexcAdapter::class,
        'bybit' => BybitAdapter::class,
        'binance' => BinanceAdapter::class,
    ];

    public static function make(string $exchange, array $credentials): ExchangeInterface
    {
        $exchange = strtolower($exchange);

        if (!isset(static::$adapters[$exchange])) {
            throw new \InvalidArgumentException("Unsupported exchange: {$exchange}");
        }

        $adapterClass = static::$adapters[$exchange];

        return new $adapterClass($credentials);
    }

    public static function supported(): array
    {
        return array_keys(static::$adapters);
    }

    public static function register(string $name, string $adapterClass): void
    {
        static::$adapters[strtolower($name)] = $adapterClass;
    }
}
