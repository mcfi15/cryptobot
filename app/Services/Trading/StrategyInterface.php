<?php

namespace App\Services\Trading;

interface StrategyInterface
{
    public function generateSignal(array $candles, array $indicators, string $marketRegime): ?array;
    public function getName(): string;
}
