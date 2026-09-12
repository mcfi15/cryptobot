<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScannerSignal extends Model
{
    use HasFactory;

    public const STATUS = [
        'detected', 'qualified', 'watching', 'entry_pending', 'entry_confirmed',
        'executed', 'expired', 'invalidated', 'rejected', 'canceled',
    ];

    protected $fillable = [
        'user_id', 'config_id', 'exchange_account_id', 'exchange', 'symbol',
        'base_asset', 'quote_asset', 'market_type', 'direction', 'status',
        'signal_score', 'ai_probability', 'entry_price', 'stop_loss', 'take_profit',
        'risk_reward', 'current_price', 'volume_24h', 'spread_pct', 'volatility_class',
        'timeframe', 'strategy', 'market_regime', 'score_quality',
        'score_breakdown', 'indicators', 'structure', 'timeframe_analysis',
        'ai_analysis', 'reasons', 'invalidation', 'meta',
        'watch', 'fingerprint', 'expires_at', 'executed_at', 'closed_at',
        'outcome', 'outcome_pnl',
    ];

    protected $casts = [
        'ai_probability' => 'decimal:2',
        'entry_price' => 'decimal:8',
        'stop_loss' => 'decimal:8',
        'take_profit' => 'decimal:8',
        'risk_reward' => 'decimal:2',
        'current_price' => 'decimal:8',
        'volume_24h' => 'decimal:8',
        'spread_pct' => 'decimal:4',
        'outcome_pnl' => 'decimal:8',
        'score_breakdown' => 'array',
        'indicators' => 'array',
        'structure' => 'array',
        'timeframe_analysis' => 'array',
        'ai_analysis' => 'array',
        'reasons' => 'array',
        'invalidation' => 'array',
        'meta' => 'array',
        'watch' => 'boolean',
        'expires_at' => 'datetime',
        'executed_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(ScannerConfig::class, 'config_id');
    }

    public function exchangeAccount(): BelongsTo
    {
        return $this->belongsTo(ExchangeAccount::class, 'exchange_account_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['detected', 'qualified', 'watching', 'entry_pending', 'entry_confirmed'], true);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast() && $this->isActive();
    }
}