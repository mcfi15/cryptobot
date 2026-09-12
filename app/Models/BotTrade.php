<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotTrade extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id', 'user_id', 'exchange_account_id', 'exchange', 'market_type',
        'symbol', 'side', 'entry_price', 'exit_price', 'quantity', 'leverage',
        'margin', 'stop_loss', 'take_profit', 'fees', 'funding', 'slippage',
        'pnl', 'pnl_percent', 'exchange_order_id', 'status', 'opened_at',
        'closed_at', 'metadata',
    ];

    protected $casts = [
        'entry_price' => 'decimal:8',
        'exit_price' => 'decimal:8',
        'quantity' => 'decimal:8',
        'margin' => 'decimal:8',
        'stop_loss' => 'decimal:8',
        'take_profit' => 'decimal:8',
        'fees' => 'decimal:8',
        'funding' => 'decimal:8',
        'slippage' => 'decimal:8',
        'pnl' => 'decimal:8',
        'pnl_percent' => 'decimal:4',
        'metadata' => 'array',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(TradingBot::class, 'bot_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exchangeAccount(): BelongsTo
    {
        return $this->belongsTo(ExchangeAccount::class);
    }

    public function isWin(): bool
    {
        return $this->pnl > 0;
    }
}
