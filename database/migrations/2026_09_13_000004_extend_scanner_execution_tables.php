<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Allow scanner-generated trades/positions that are not tied to a TradingBot.
        Schema::table('bot_trades', function (Blueprint $table) {
            $table->dropForeign(['bot_id']);
            $table->unsignedBigInteger('bot_id')->nullable()->change();
            $table->foreign('bot_id')->references('id')->on('trading_bots')->nullOnDelete();

            $table->string('source')->default('bot')->after('bot_id');
            $table->unsignedBigInteger('signal_id')->nullable()->after('source');
            $table->index('signal_id');
        });

        Schema::table('bot_positions', function (Blueprint $table) {
            $table->dropForeign(['bot_id']);
            $table->unsignedBigInteger('bot_id')->nullable()->change();
            $table->foreign('bot_id')->references('id')->on('trading_bots')->nullOnDelete();

            $table->unsignedBigInteger('signal_id')->nullable()->after('bot_id');
            $table->index('signal_id');
        });
    }

    public function down(): void
    {
        Schema::table('bot_trades', function (Blueprint $table) {
            $table->dropIndex(['signal_id']);
            $table->dropColumn(['signal_id']);
            $table->dropColumn('source');
            $table->dropForeign(['bot_id']);
            $table->unsignedBigInteger('bot_id')->nullable(false)->change();
            $table->foreign('bot_id')->references('id')->on('trading_bots')->cascadeOnDelete();
        });

        Schema::table('bot_positions', function (Blueprint $table) {
            $table->dropIndex(['signal_id']);
            $table->dropColumn('signal_id');
            $table->dropForeign(['bot_id']);
            $table->unsignedBigInteger('bot_id')->nullable(false)->change();
            $table->foreign('bot_id')->references('id')->on('trading_bots')->cascadeOnDelete();
        });
    }
};