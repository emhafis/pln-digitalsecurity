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
        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_record_id')
                ->constrained('attendance_records')
                ->restrictOnDelete();
            $table->foreignId('requested_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('requested_by_role', 20);
            $table->text('reason');

            $table->dateTime('requested_check_in_at')->nullable();
            $table->dateTime('requested_check_out_at')->nullable();
            $table->string('requested_status', 20)->nullable();

            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            $table->dateTime('before_check_in_at')->nullable();
            $table->dateTime('before_check_out_at')->nullable();
            $table->string('before_status', 20)->nullable();
            $table->dateTime('applied_check_in_at')->nullable();
            $table->dateTime('applied_check_out_at')->nullable();
            $table->string('applied_status', 20)->nullable();

            $table->timestamps();

            $table->index(['attendance_record_id', 'status'], 'attendance_corrections_record_status_idx');
            $table->index('requested_by', 'attendance_corrections_requested_by_idx');
            $table->index('reviewed_by', 'attendance_corrections_reviewed_by_idx');
            $table->index('status', 'attendance_corrections_status_idx');
            $table->index(['attendance_record_id', 'created_at'], 'attendance_corrections_record_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
    }
};
