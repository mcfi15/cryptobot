<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function exchangeAccounts(): HasMany
    {
        return $this->hasMany(ExchangeAccount::class);
    }

    public function bots(): HasMany
    {
        return $this->hasMany(TradingBot::class);
    }

    public function trades(): HasMany
    {
        return $this->hasMany(BotTrade::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(BotPosition::class);
    }
}
