<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotPosition extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id', 'user_id', 'exchange_account_id', 'signal_id', 'exchange', 'market_type',
        'symbol', 'side', 'quantity', 'entry_price', 'current_price',
        'leverage', 'margin', 'liquidation_price', 'unrealized_pnl',
        'stop_loss', 'take_profit', 'status', 'opened_at',
        'trail_high', 'trail_low',
    ];

    protected $casts = [
        'quantity' => 'decimal:8',
        'entry_price' => 'decimal:8',
        'current_price' => 'decimal:8',
        'margin' => 'decimal:8',
        'liquidation_price' => 'decimal:8',
        'unrealized_pnl' => 'decimal:8',
        'stop_loss' => 'decimal:8',
        'take_profit' => 'decimal:8',
        'trail_high' => 'decimal:8',
        'trail_low' => 'decimal:8',
        'opened_at' => 'datetime',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(TradingBot::class, 'bot_id');
    }
}
