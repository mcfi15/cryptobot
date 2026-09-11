<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trading_bots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exchange_account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('market_type', ['spot', 'futures'])->default('spot');
            $table->enum('mode', ['manual', 'signal_only', 'paper', 'live'])->default('paper');
            $table->string('strategy');
            $table->json('symbols');
            $table->json('timeframes');
            $table->decimal('risk_per_trade', 5, 2)->default(0.5);
            $table->decimal('max_daily_loss', 5, 2)->default(2.0);
            $table->integer('max_open_positions')->default(3);
            $table->decimal('max_position_size', 20, 8)->default(1000);
            $table->integer('max_leverage')->default(1);
            $table->decimal('ai_threshold', 5, 2)->default(70);
            $table->decimal('signal_threshold', 5, 2)->default(75);
            $table->json('settings')->nullable();
            $table->enum('status', ['running', 'paused', 'stopped', 'error', 'risk_locked'])->default('stopped');
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('exchange_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_bots');
    }
};
