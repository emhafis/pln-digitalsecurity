<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patrol_session_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patrol_session_id')
                ->constrained('patrol_sessions')
                ->restrictOnDelete();
            $table->foreignId('patrol_point_id')
                ->constrained('patrol_points')
                ->restrictOnDelete();
            $table->string('patrol_point_name');
            $table->uuid('patrol_point_qr_code');
            $table->unsignedInteger('sequence');
            $table->timestamps();

            $table->unique(
                ['patrol_session_id', 'patrol_point_id'],
                'patrol_session_points_session_point_unique'
            );
            $table->unique(
                ['patrol_session_id', 'sequence'],
                'patrol_session_points_session_sequence_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patrol_session_points');
    }
};
