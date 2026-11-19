<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patrol_scan_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patrol_scan_id')
                ->constrained('patrol_scans')
                ->restrictOnDelete();
            $table->string('photo_path');
            $table->unsignedInteger('sequence');
            $table->timestamps();

            $table->unique(
                ['patrol_scan_id', 'sequence'],
                'patrol_scan_photos_scan_sequence_unique'
            );
            $table->index('patrol_scan_id', 'patrol_scan_photos_scan_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patrol_scan_photos');
    }
};
