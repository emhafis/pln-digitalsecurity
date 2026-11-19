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
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_period_id')
                ->constrained('schedule_periods')
                ->restrictOnDelete();
            $table->foreignId('schedule_period_member_id')
                ->constrained('schedule_period_members')
                ->restrictOnDelete();
            $table->foreignId('security_id')
                ->constrained('securities')
                ->restrictOnDelete();
            $table->foreignId('shift_id')
                ->nullable()
                ->constrained('shifts')
                ->restrictOnDelete();
            $table->foreignId('schedule_pattern_id')
                ->nullable()
                ->constrained('schedule_patterns')
                ->restrictOnDelete();
            $table->unsignedSmallInteger('pattern_day_order')->nullable();
            $table->date('date');
            $table->enum('source', ['system_generated', 'adjustment'])->default('system_generated');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique(['security_id', 'date'], 'schedules_security_date_unique');
            $table->index(['schedule_period_id', 'date'], 'schedules_period_date_idx');
            $table->index(['schedule_period_member_id', 'date'], 'schedules_member_date_idx');
            $table->index('shift_id', 'schedules_shift_idx');
            $table->index('schedule_pattern_id', 'schedules_pattern_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
