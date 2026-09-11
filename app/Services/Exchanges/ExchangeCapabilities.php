<?php

namespace App\Services\Exchanges;

class ExchangeCapabilities
{
    public function __construct(
        public bool $spot = true,
        public bool $futures = false,
        public bool $margin = false,
        public bool $short = false,
        public bool $leverage = false,
        public bool $stopLoss = false,
        public bool $takeProfit = false,
        public bool $trailingStop = false,
        public bool $reduceOnly = false,
        public bool $hedgeMode = false,
        public bool $oneWayMode = true,
        public bool $funding = false,
        public bool $websocket = false,
        public int $maxLeverage = 1,
        public array $supportedIntervals = ['1m','5m','15m','1h','4h','1d'],
    ) {}

    public function toArray(): array
    {
        return [
            'spot' => $this->spot,
            'futures' => $this->futures,
            'margin' => $this->margin,
            'short' => $this->short,
            'leverage' => $this->leverage,
            'stop_loss' => $this->stopLoss,
            'take_profit' => $this->takeProfit,
            'trailing_stop' => $this->trailingStop,
            'reduce_only' => $this->reduceOnly,
            'hedge_mode' => $this->hedgeMode,
            'one_way_mode' => $this->oneWayMode,
            'funding' => $this->funding,
            'websocket' => $this->websocket,
            'max_leverage' => $this->maxLeverage,
            'supported_intervals' => $this->supportedIntervals,
        ];
    }
}
