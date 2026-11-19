<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patrol_session_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patrol_session_id')
                ->constrained('patrol_sessions')
                ->restrictOnDelete();
            $table->foreignId('schedule_id')
                ->constrained('schedules')
                ->restrictOnDelete();
            $table->foreignId('security_id')
                ->constrained('securities')
                ->restrictOnDelete();
            $table->string('security_name');
            $table->string('security_registration_number')->nullable();
            $table->timestamps();

            $table->unique(
                ['patrol_session_id', 'security_id'],
                'patrol_session_members_session_security_unique'
            );
            $table->unique('schedule_id', 'patrol_session_members_schedule_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patrol_session_members');
    }
};
