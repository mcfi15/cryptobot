<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScannerWatchlistEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'config_id', 'symbol', 'exchange', 'market_type', 'active', 'sort_order',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(ScannerConfig::class, 'config_id');
    }
}