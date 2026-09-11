<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ml_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('version');
            $table->string('algorithm');
            $table->json('features');
            $table->string('training_period');
            $table->string('validation_period')->nullable();
            $table->string('test_period')->nullable();
            $table->json('metrics')->nullable();
            $table->string('model_path');
            $table->enum('status', ['training', 'ready', 'active', 'archived', 'failed'])->default('training');
            $table->timestamps();

            $table->index(['name', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ml_models');
    }
};
