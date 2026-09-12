<?php

namespace App\Services\Scanner;

class IntervalMapper
{
    protected static array $map = [
        'mexc' => ['1m' => '1m', '3m' => '3m', '5m' => '5m', '15m' => '15m', '30m' => '30m', '1h' => '1h', '2h' => '2h', '4h' => '4h', '1d' => '1d'],
        'binance' => ['1m' => '1m', '3m' => '3m', '5m' => '5m', '15m' => '15m', '30m' => '30m', '1h' => '1h', '2h' => '2h', '4h' => '4h', '1d' => '1d'],
        'bybit' => ['1m' => '1', '3m' => '3', '5m' => '5', '15m' => '15', '30m' => '30', '1h' => '60', '2h' => '120', '4h' => '240', '1d' => 'D'],
    ];

    public static function canonical(string $exchange, string $interval): string
    {
        return static::$map[strtolower($exchange)][$interval] ?? $interval;
    }

    public static function supports(string $exchange, string $interval): bool
    {
        return isset(static::$map[strtolower($exchange)][$interval]);
    }
}