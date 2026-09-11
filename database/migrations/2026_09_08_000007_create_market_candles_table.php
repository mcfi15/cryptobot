<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_candles', function (Blueprint $table) {
            $table->id();
            $table->string('exchange');
            $table->string('symbol');
            $table->enum('market_type', ['spot', 'futures']);
            $table->string('timeframe');
            $table->decimal('open', 20, 8);
            $table->decimal('high', 20, 8);
            $table->decimal('low', 20, 8);
            $table->decimal('close', 20, 8);
            $table->decimal('volume', 20, 8);
            $table->unsignedBigInteger('timestamp');
            $table->timestamps();

            $table->unique(['exchange', 'symbol', 'market_type', 'timeframe', 'timestamp'], 'mc_exch_sym_mkttf_ts_unique');
            $table->index(['exchange', 'symbol', 'timeframe'], 'mc_exch_sym_tf_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_candles');
    }
};
