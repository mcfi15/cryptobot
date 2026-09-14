<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScannerActivity extends Model
{
    use HasFactory;

    protected $table = 'scanner_activity_logs';

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'config_id', 'signal_id', 'level', 'event', 'message', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(ScannerConfig::class, 'config_id');
    }

    public function signal(): BelongsTo
    {
        return $this->belongsTo(ScannerSignal::class, 'signal_id');
    }
}