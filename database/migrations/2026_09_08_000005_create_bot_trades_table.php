<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained('trading_bots')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exchange_account_id')->constrained();
            $table->string('exchange');
            $table->enum('market_type', ['spot', 'futures']);
            $table->string('symbol');
            $table->enum('side', ['long', 'short', 'buy', 'sell']);
            $table->decimal('entry_price', 20, 8);
            $table->decimal('exit_price', 20, 8)->nullable();
            $table->decimal('quantity', 20, 8);
            $table->integer('leverage')->default(1);
            $table->decimal('margin', 20, 8)->nullable();
            $table->decimal('stop_loss', 20, 8)->nullable();
            $table->decimal('take_profit', 20, 8)->nullable();
            $table->decimal('fees', 20, 8)->default(0);
            $table->decimal('funding', 20, 8)->default(0);
            $table->decimal('slippage', 20, 8)->default(0);
            $table->decimal('pnl', 20, 8)->nullable();
            $table->decimal('pnl_percent', 10, 4)->nullable();
            $table->string('exchange_order_id')->nullable();
            $table->enum('status', ['open', 'closed', 'cancelled', 'rejected'])->default('open');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['bot_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('symbol');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_trades');
    }
};
