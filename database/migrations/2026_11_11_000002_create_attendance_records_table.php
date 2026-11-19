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
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('schedule_id')
                ->unique('attendance_records_schedule_id_unique')
                ->constrained('schedules')
                ->restrictOnDelete();
            $table->foreignId('schedule_period_id')
                ->constrained('schedule_periods')
                ->restrictOnDelete();
            $table->foreignId('security_id')
                ->constrained('securities')
                ->restrictOnDelete();
            $table->foreignId('security_post_id')
                ->constrained('security_posts')
                ->restrictOnDelete();
            $table->foreignId('shift_id')
                ->constrained('shifts')
                ->restrictOnDelete();

            $table->date('work_date');
            $table->string('security_name');
            $table->string('security_registration_number')->nullable();
            $table->foreignId('unit_id')
                ->constrained('units')
                ->restrictOnDelete();
            $table->string('unit_name');
            $table->foreignId('security_team_id')
                ->constrained('security_teams')
                ->restrictOnDelete();
            $table->string('security_team_name');
            $table->string('security_post_name');
            $table->string('shift_name');
            $table->string('shift_code');
            $table->string('shift_type', 16);
            $table->time('shift_start_time')->nullable();
            $table->time('shift_end_time')->nullable();
            $table->unsignedTinyInteger('shift_end_day_offset')->default(0);
            $table->dateTime('scheduled_start_at')->nullable();
            $table->dateTime('scheduled_end_at')->nullable();

            $table->decimal('post_latitude', 10, 8)->nullable();
            $table->decimal('post_longitude', 11, 8)->nullable();

            $table->unsignedSmallInteger('setting_gps_radius_meters');
            $table->unsignedSmallInteger('setting_check_in_open_minutes');
            $table->unsignedSmallInteger('setting_late_tolerance_minutes');
            $table->unsignedSmallInteger('setting_early_checkout_tolerance_minutes');
            $table->unsignedSmallInteger('setting_checkout_close_minutes');
            $table->unsignedSmallInteger('setting_max_gps_accuracy_meters');

            $table->dateTime('check_in_at')->nullable();
            $table->dateTime('check_in_captured_at')->nullable();
            $table->decimal('check_in_latitude', 10, 8)->nullable();
            $table->decimal('check_in_longitude', 11, 8)->nullable();
            $table->decimal('check_in_accuracy_meters', 8, 2)->nullable();
            $table->decimal('check_in_distance_meters', 10, 2)->nullable();
            $table->boolean('check_in_geofence_valid')->nullable();

            $table->dateTime('check_out_at')->nullable();
            $table->dateTime('check_out_captured_at')->nullable();
            $table->decimal('check_out_latitude', 10, 8)->nullable();
            $table->decimal('check_out_longitude', 11, 8)->nullable();
            $table->decimal('check_out_accuracy_meters', 8, 2)->nullable();
            $table->decimal('check_out_distance_meters', 10, 2)->nullable();
            $table->boolean('check_out_geofence_valid')->nullable();

            $table->dateTime('corrected_check_in_at')->nullable();
            $table->dateTime('corrected_check_out_at')->nullable();
            $table->string('corrected_status', 20)->nullable();

            $table->string('status', 20)->default('scheduled');
            $table->dateTime('absent_marked_at')->nullable();
            $table->dateTime('snapshot_locked_at')->nullable();
            $table->dateTime('last_synced_at')->nullable();

            $table->timestamps();

            $table->index(['security_id', 'work_date'], 'attendance_records_security_work_date_idx');
            $table->index(['schedule_period_id', 'work_date'], 'attendance_records_period_work_date_idx');
            $table->index(['security_post_id', 'work_date'], 'attendance_records_post_work_date_idx');
            $table->index(['unit_id', 'work_date'], 'attendance_records_unit_work_date_idx');
            $table->index('status', 'attendance_records_status_idx');
            $table->index('shift_type', 'attendance_records_shift_type_idx');
            $table->index('work_date', 'attendance_records_work_date_idx');
            $table->index('scheduled_end_at', 'attendance_records_scheduled_end_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
