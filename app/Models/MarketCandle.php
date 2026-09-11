<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketCandle extends Model
{
    use HasFactory;

    protected $fillable = [
        'exchange', 'symbol', 'market_type', 'timeframe',
        'open', 'high', 'low', 'close', 'volume', 'timestamp',
    ];

    protected $casts = [
        'open' => 'decimal:8',
        'high' => 'decimal:8',
        'low' => 'decimal:8',
        'close' => 'decimal:8',
        'volume' => 'decimal:8',
    ];
}
