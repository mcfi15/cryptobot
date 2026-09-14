<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScannerConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'scan_mode', 'exchanges', 'market_type',
        'min_signal_score', 'min_ai_probability', 'min_risk_reward', 'min_volume_24h',
        'max_spread_pct', 'max_volatility', 'max_markets',
        'timeframes', 'preferred_assets', 'quote_assets',
        'auto_trading', 'risk_per_trade', 'max_daily_loss', 'max_open_positions',
        'max_leverage', 'signal_expiry_minutes', 'trading_mode', 'paper_mode',
        'status', 'paused_reason', 'peak_equity',
        'cooldown_minutes', 'max_consecutive_losses', 'max_hold_hours',
        'trailing_enabled', 'trailing_activation_pct', 'trailing_distance_pct', 'break_even_pct',
        'scanned_markets', 'qualified_signals', 'last_scan_at', 'last_scan_duration_ms',
    ];

    protected $casts = [
        'exchanges' => 'array',
        'timeframes' => 'array',
        'preferred_assets' => 'array',
        'quote_assets' => 'array',
        'min_ai_probability' => 'decimal:2',
        'min_risk_reward' => 'decimal:2',
        'min_volume_24h' => 'decimal:2',
        'max_spread_pct' => 'decimal:3',
        'auto_trading' => 'boolean',
        'risk_per_trade' => 'decimal:2',
        'max_daily_loss' => 'decimal:2',
        'paper_mode' => 'boolean',
        'trailing_enabled' => 'boolean',
        'trailing_activation_pct' => 'decimal:3',
        'trailing_distance_pct' => 'decimal:3',
        'break_even_pct' => 'decimal:3',
        'peak_equity' => 'decimal:8',
        'max_hold_hours' => 'integer',
        'cooldown_minutes' => 'integer',
        'max_consecutive_losses' => 'integer',
        'last_scan_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function signals(): HasMany
    {
        return $this->hasMany(ScannerSignal::class, 'config_id');
    }

    public function watchlist(): HasMany
    {
        return $this->hasMany(ScannerWatchlistEntry::class, 'config_id');
    }

    public static function forUser(int $userId): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId],
            [
                'status' => 'stopped',
                'timeframes' => ['4h', '1h', '15m'],
                'quote_assets' => ['USDT'],
            ]
        );
    }

    public function timeframeList(): array
    {
        return $this->timeframes ?: ['4h', '1h', '15m'];
    }
}