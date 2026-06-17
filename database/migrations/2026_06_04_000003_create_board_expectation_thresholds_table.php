<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_expectation_thresholds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('board_id', 64);
            $table->decimal('threshold', 5, 2);
            $table->timestamps();

            $table->index(['user_id', 'board_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_expectation_thresholds');
    }
};
