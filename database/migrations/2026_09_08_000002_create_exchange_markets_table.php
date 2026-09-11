<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_markets', function (Blueprint $table) {
            $table->id();
            $table->string('exchange');
            $table->string('symbol');
            $table->string('base_asset');
            $table->string('quote_asset');
            $table->enum('market_type', ['spot', 'futures'])->default('spot');
            $table->integer('price_precision')->default(8);
            $table->integer('quantity_precision')->default(8);
            $table->decimal('min_quantity', 20, 8)->default(0);
            $table->decimal('max_quantity', 20, 8)->default(0);
            $table->decimal('tick_size', 20, 8)->default(0);
            $table->decimal('min_notional', 20, 8)->default(10);
            $table->integer('leverage_limit')->default(1);
            $table->enum('status', ['trading', 'halted'])->default('trading');
            $table->timestamps();

            $table->unique(['exchange', 'symbol', 'market_type']);
            $table->index('exchange');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_markets');
    }
};
