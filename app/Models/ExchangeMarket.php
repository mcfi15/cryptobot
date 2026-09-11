<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeMarket extends Model
{
    use HasFactory;

    protected $fillable = [
        'exchange', 'symbol', 'base_asset', 'quote_asset', 'market_type',
        'price_precision', 'quantity_precision', 'min_quantity', 'max_quantity',
        'tick_size', 'min_notional', 'leverage_limit', 'status',
    ];

    protected $casts = [
        'min_quantity' => 'decimal:8',
        'max_quantity' => 'decimal:8',
        'tick_size' => 'decimal:8',
        'min_notional' => 'decimal:8',
    ];
}
