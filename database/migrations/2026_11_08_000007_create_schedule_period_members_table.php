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
        Schema::create('schedule_period_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_period_id')
                ->constrained('schedule_periods')
                ->cascadeOnDelete();
            $table->foreignId('security_id')
                ->constrained('securities')
                ->restrictOnDelete();
            $table->foreignId('schedule_pattern_id')
                ->nullable()
                ->constrained('schedule_patterns')
                ->restrictOnDelete();
            $table->unsignedSmallInteger('start_offset')->default(0);
            $table->timestamps();

            $table->unique(
                ['schedule_period_id', 'security_id'],
                'schedule_period_members_period_security_unique'
            );
            $table->index('security_id', 'schedule_period_members_security_idx');
            $table->index('schedule_pattern_id', 'schedule_period_members_pattern_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_period_members');
    }
};
