<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patrol_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_period_id')
                ->constrained('schedule_periods')
                ->restrictOnDelete();
            $table->foreignId('security_team_id')
                ->constrained('security_teams')
                ->restrictOnDelete();
            $table->foreignId('security_post_id')
                ->constrained('security_posts')
                ->restrictOnDelete();
            $table->foreignId('unit_id')
                ->constrained('units')
                ->restrictOnDelete();
            $table->foreignId('shift_id')
                ->constrained('shifts')
                ->restrictOnDelete();
            $table->date('work_date');
            $table->string('team_name');
            $table->string('team_code');
            $table->string('post_name');
            $table->string('post_code')->nullable();
            $table->string('unit_name');
            $table->string('unit_code');
            $table->string('shift_name');
            $table->string('shift_code');
            $table->string('shift_type');
            $table->time('shift_start_time')->nullable();
            $table->time('shift_end_time')->nullable();
            $table->unsignedTinyInteger('shift_end_day_offset');
            $table->string('status');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['schedule_period_id', 'work_date', 'shift_id'],
                'patrol_sessions_period_date_shift_unique'
            );
            $table->index('work_date', 'patrol_sessions_work_date_idx');
            $table->index('status', 'patrol_sessions_status_idx');
            $table->index('unit_id', 'patrol_sessions_unit_idx');
            $table->index('security_post_id', 'patrol_sessions_post_idx');
            $table->index('security_team_id', 'patrol_sessions_team_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patrol_sessions');
    }
};
