<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scanner_watchlist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('config_id')->nullable()->constrained('scanner_configs')->cascadeOnDelete();
            $table->string('symbol');
            $table->string('exchange')->nullable();
            $table->string('market_type')->default('both'); // spot|futures|both
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'symbol', 'exchange', 'market_type'], 'wl_user_sym_ex_mkt');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scanner_watchlist_entries');
    }
};