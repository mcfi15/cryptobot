<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TradingBot extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'exchange_account_id', 'name', 'market_type', 'mode',
        'strategy', 'symbols', 'timeframes', 'risk_per_trade', 'max_daily_loss',
        'max_open_positions', 'max_position_size', 'max_leverage',
        'ai_threshold', 'signal_threshold', 'settings', 'status',
    ];

    protected $casts = [
        'symbols' => 'array',
        'timeframes' => 'array',
        'settings' => 'array',
        'risk_per_trade' => 'decimal:2',
        'max_daily_loss' => 'decimal:2',
        'max_position_size' => 'decimal:8',
        'ai_threshold' => 'decimal:2',
        'signal_threshold' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exchangeAccount(): BelongsTo
    {
        return $this->belongsTo(ExchangeAccount::class);
    }

    public function signals(): HasMany
    {
        return $this->hasMany(BotSignal::class, 'bot_id');
    }

    public function trades(): HasMany
    {
        return $this->hasMany(BotTrade::class, 'bot_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(BotPosition::class, 'bot_id');
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isLive(): bool
    {
        return $this->mode === 'live';
    }
}
