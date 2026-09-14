<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-user scanner risk + exit-management settings.
        Schema::table('scanner_configs', function (Blueprint $table) {
            $table->string('status')->default('stopped')->change(); // running | stopped | paused
            $table->string('paused_reason')->nullable()->after('status');
            $table->decimal('peak_equity', 20, 8)->nullable()->after('paused_reason');

            $table->integer('cooldown_minutes')->default(0)->after('max_open_positions');
            $table->integer('max_consecutive_losses')->default(3)->after('cooldown_minutes');
            $table->integer('max_hold_hours')->nullable()->after('max_consecutive_losses');

            $table->boolean('trailing_enabled')->default(false)->after('signal_expiry_minutes');
            $table->decimal('trailing_activation_pct', 6, 3)->default(0.500)->after('trailing_enabled');
            $table->decimal('trailing_distance_pct', 6, 3)->default(0.400)->after('trailing_activation_pct');
            $table->decimal('break_even_pct', 6, 3)->nullable()->after('trailing_distance_pct');
        });

        // Trail watermark tracking for managing trailing stops.
        Schema::table('bot_positions', function (Blueprint $table) {
            $table->decimal('trail_high', 20, 8)->nullable()->after('take_profit');
            $table->decimal('trail_low', 20, 8)->nullable()->after('trail_high');
        });

        // Why a trade was closed (tp_hit / sl_hit / trailing_stop / time_exit / emergency).
        Schema::table('bot_trades', function (Blueprint $table) {
            $table->string('exit_reason')->nullable()->after('closed_at');
        });

        // User-facing bot activity feed (discovery -> decision -> execution -> close).
        Schema::create('scanner_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('config_id')->nullable()->index();
            $table->unsignedBigInteger('signal_id')->nullable()->index();
            $table->string('level')->default('info'); // info | success | warning | danger
            $table->string('event', 100)->index();
            $table->string('message', 500);
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scanner_activity_logs');

        Schema::table('bot_trades', function (Blueprint $table) {
            $table->dropColumn('exit_reason');
        });

        Schema::table('bot_positions', function (Blueprint $table) {
            $table->dropColumn(['trail_high', 'trail_low']);
        });

        Schema::table('scanner_configs', function (Blueprint $table) {
            $table->dropColumn([
                'paused_reason', 'peak_equity',
                'cooldown_minutes', 'max_consecutive_losses', 'max_hold_hours',
                'trailing_enabled', 'trailing_activation_pct', 'trailing_distance_pct', 'break_even_pct',
            ]);
            $table->string('status')->default('stopped')->change(); // running | stopped
        });
    }
};