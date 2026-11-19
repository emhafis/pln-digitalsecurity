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
        Schema::create('attendance_generation_anomalies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')
                ->constrained('schedules')
                ->restrictOnDelete();
            $table->foreignId('schedule_period_id')
                ->constrained('schedule_periods')
                ->restrictOnDelete();
            $table->foreignId('security_id')
                ->nullable()
                ->constrained('securities')
                ->restrictOnDelete();
            $table->date('work_date')->nullable();
            $table->string('anomaly_type', 50);
            $table->text('details')->nullable();
            $table->dateTime('detected_at');
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('resolved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->unique(
                ['schedule_id', 'anomaly_type'],
                'attendance_generation_anomalies_schedule_type_unique'
            );
            $table->index('resolved_at', 'attendance_generation_anomalies_resolved_at_idx');
            $table->index('anomaly_type', 'attendance_generation_anomalies_type_idx');
            $table->index('schedule_period_id', 'attendance_generation_anomalies_period_idx');
            $table->index('work_date', 'attendance_generation_anomalies_work_date_idx');
            $table->index('security_id', 'attendance_generation_anomalies_security_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_generation_anomalies');
    }
};
