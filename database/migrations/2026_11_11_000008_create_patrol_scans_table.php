<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patrol_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patrol_session_id')
                ->constrained('patrol_sessions')
                ->restrictOnDelete();
            $table->foreignId('patrol_point_id')
                ->constrained('patrol_points')
                ->restrictOnDelete();
            $table->foreignId('security_id')
                ->constrained('securities')
                ->restrictOnDelete();
            $table->string('patrol_point_name');
            $table->uuid('patrol_point_qr_code');
            $table->string('security_name');
            $table->string('security_registration_number')->nullable();
            $table->timestamp('scanned_at');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->boolean('has_incident')->default(false);
            $table->text('incident_description')->nullable();
            $table->timestamps();

            $table->unique(
                ['patrol_session_id', 'patrol_point_id'],
                'patrol_scans_session_point_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patrol_scans');
    }
};
