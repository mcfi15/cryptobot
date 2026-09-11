<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained('trading_bots')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exchange_account_id')->constrained();
            $table->string('exchange');
            $table->enum('market_type', ['spot', 'futures']);
            $table->string('symbol');
            $table->enum('side', ['long', 'short', 'buy', 'sell']);
            $table->decimal('quantity', 20, 8);
            $table->decimal('entry_price', 20, 8);
            $table->decimal('current_price', 20, 8)->nullable();
            $table->integer('leverage')->default(1);
            $table->decimal('margin', 20, 8)->nullable();
            $table->decimal('liquidation_price', 20, 8)->nullable();
            $table->decimal('unrealized_pnl', 20, 8)->nullable();
            $table->decimal('stop_loss', 20, 8)->nullable();
            $table->decimal('take_profit', 20, 8)->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();

            $table->index(['bot_id', 'status']);
            $table->index('symbol');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_positions');
    }
};
