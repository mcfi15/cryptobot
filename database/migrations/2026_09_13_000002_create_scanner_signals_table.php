<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scanner_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('config_id')->nullable()->constrained('scanner_configs')->nullOnDelete();
            $table->unsignedBigInteger('exchange_account_id')->nullable();
            $table->string('exchange');
            $table->string('symbol');
            $table->string('base_asset')->nullable();
            $table->string('quote_asset')->nullable();
            $table->enum('market_type', ['spot', 'futures']);
            $table->enum('direction', ['long', 'short', 'buy', 'sell']);
            $table->string('status')->default('qualified');
            $table->integer('signal_score')->default(0);
            $table->decimal('ai_probability', 5, 2)->nullable();
            $table->decimal('entry_price', 20, 8);
            $table->decimal('stop_loss', 20, 8);
            $table->decimal('take_profit', 20, 8);
            $table->decimal('risk_reward', 5, 2);
            $table->decimal('current_price', 20, 8)->nullable();
            $table->decimal('volume_24h', 20, 8)->nullable();
            $table->decimal('spread_pct', 8, 4)->nullable();
            $table->string('volatility_class')->nullable();
            $table->string('timeframe')->default('4h');
            $table->string('strategy');
            $table->string('market_regime')->nullable();
            $table->string('score_quality')->default('watch'); // exceptional|strong|good|weak|reject
            $table->json('score_breakdown')->nullable();
            $table->json('indicators')->nullable();
            $table->json('structure')->nullable();
            $table->json('timeframe_analysis')->nullable();
            $table->json('ai_analysis')->nullable();
            $table->json('reasons')->nullable();
            $table->json('invalidation')->nullable();
            $table->json('meta')->nullable();
            $table->boolean('watch')->default(false);
            $table->string('fingerprint')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('outcome')->default('pending'); // pending|tp_hit|sl_hit|expired|invalidated|canceled|closed
            $table->decimal('outcome_pnl', 20, 8)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'signal_score', 'status']);
            $table->index(['user_id', 'fingerprint', 'status']);
            $table->index('expires_at');
            $table->index('watch');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scanner_signals');
    }
};