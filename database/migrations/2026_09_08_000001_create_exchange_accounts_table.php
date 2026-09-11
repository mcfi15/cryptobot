<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('exchange'); // mexc, bybit, binance
            $table->string('label');
            $table->text('encrypted_credentials');
            $table->enum('status', ['connected', 'disconnected', 'error'])->default('disconnected');
            $table->boolean('trading_enabled')->default(false);
            $table->timestamp('last_connection_check')->nullable();
            $table->json('capabilities')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'exchange']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_accounts');
    }
};
