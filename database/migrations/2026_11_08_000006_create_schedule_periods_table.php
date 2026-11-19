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
        Schema::create('schedule_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')
                ->constrained('units')
                ->restrictOnDelete();
            $table->foreignId('security_post_id')
                ->constrained('security_posts')
                ->restrictOnDelete();
            $table->foreignId('security_team_id')
                ->constrained('security_teams')
                ->restrictOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['draft', 'active', 'completed'])->default('draft');
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();

            $table->index(
                ['security_team_id', 'start_date', 'end_date'],
                'schedule_periods_team_dates_idx'
            );
            $table->index(['unit_id', 'status'], 'schedule_periods_unit_status_idx');
            $table->index(['security_post_id', 'status'], 'schedule_periods_post_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_periods');
    }
};
