<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('schedule_pattern_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_pattern_id')
                ->constrained('schedule_patterns')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('day_order');
            $table->foreignId('shift_id')
                ->constrained('shifts')
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['schedule_pattern_id', 'day_order'],
                'schedule_pattern_details_pattern_day_unique'
            );
            $table->index('shift_id', 'schedule_pattern_details_shift_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_pattern_details');
    }
};
