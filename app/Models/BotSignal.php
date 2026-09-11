<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotSignal extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_id', 'symbol', 'market_type', 'direction', 'confidence',
        'signal_score', 'entry_price', 'stop_loss', 'take_profit',
        'risk_reward', 'strategy', 'market_regime', 'prediction',
        'features', 'status',
    ];

    protected $casts = [
        'confidence' => 'decimal:2',
        'entry_price' => 'decimal:8',
        'stop_loss' => 'decimal:8',
        'take_profit' => 'decimal:8',
        'risk_reward' => 'decimal:2',
        'prediction' => 'array',
        'features' => 'array',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(TradingBot::class);
    }
}
