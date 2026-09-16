<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scanner_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('scan_mode')->default('all'); // all | watchlist | hybrid
            $table->json('exchanges')->nullable();        // null = all connected
            $table->string('market_type')->default('both'); // spot | futures | both
            $table->integer('min_signal_score')->default(55);
            $table->decimal('min_ai_probability', 5, 2)->default(55.00);
            $table->decimal('min_risk_reward', 5, 2)->default(1.50);
            $table->decimal('min_volume_24h', 20, 2)->default(1000000.00);
            $table->decimal('max_spread_pct', 6, 3)->default(0.500);
            $table->string('max_volatility')->default('high'); // low|normal|high|extreme
            $table->integer('max_markets')->default(100);
            $table->json('timeframes')->nullable();
            $table->json('preferred_assets')->nullable();
            $table->json('quote_assets')->nullable();
            $table->boolean('auto_trading')->default(false);
            $table->decimal('risk_per_trade', 5, 2)->default(0.50);
            $table->decimal('max_daily_loss', 5, 2)->default(2.00);
            $table->integer('max_open_positions')->default(3);
            $table->integer('max_leverage')->default(20);
            $table->integer('signal_expiry_minutes')->default(30);
            $table->string('trading_mode')->default('paper'); // paper | live
            $table->boolean('paper_mode')->default(true);
            $table->string('status')->default('stopped'); // running | stopped
            $table->integer('scanned_markets')->default(0);
            $table->integer('qualified_signals')->default(0);
            $table->timestamp('last_scan_at')->nullable();
            $table->integer('last_scan_duration_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scanner_configs');
    }
};