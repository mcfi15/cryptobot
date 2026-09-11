<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExchangeAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'exchange', 'label', 'encrypted_credentials',
        'status', 'trading_enabled', 'last_connection_check',
        'capabilities', 'metadata',
    ];

    protected $casts = [
        'trading_enabled' => 'boolean',
        'capabilities' => 'array',
        'metadata' => 'array',
        'last_connection_check' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bots(): HasMany
    {
        return $this->hasMany(TradingBot::class);
    }

    public function getDecryptedCredentials(): array
    {
        return json_decode(decrypt($this->encrypted_credentials), true);
    }

    public function setCredentials(array $credentials): void
    {
        $this->encrypted_credentials = encrypt(json_encode($credentials));
    }
}
