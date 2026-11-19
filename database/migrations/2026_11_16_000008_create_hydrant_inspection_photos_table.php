<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hydrant_inspection_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hydrant_inspection_id')->constrained('hydrant_inspections')->cascadeOnDelete();
            $table->string('photo_path', 255);
            $table->string('caption', 255)->nullable();
            $table->timestamps();

            $table->index('hydrant_inspection_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hydrant_inspection_photos');
    }
};
