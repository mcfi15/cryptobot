<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained('trading_bots')->cascadeOnDelete();
            $table->string('symbol');
            $table->enum('market_type', ['spot', 'futures']);
            $table->enum('direction', ['long', 'short', 'buy', 'sell']);
            $table->decimal('confidence', 5, 2);
            $table->integer('signal_score');
            $table->decimal('entry_price', 20, 8);
            $table->decimal('stop_loss', 20, 8);
            $table->decimal('take_profit', 20, 8);
            $table->decimal('risk_reward', 5, 2);
            $table->string('strategy');
            $table->string('market_regime')->nullable();
            $table->json('prediction')->nullable();
            $table->json('features')->nullable();
            $table->enum('status', ['pending', 'executed', 'skipped', 'expired', 'rejected'])->default('pending');
            $table->timestamps();

            $table->index(['bot_id', 'status']);
            $table->index('symbol');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_signals');
    }
};
