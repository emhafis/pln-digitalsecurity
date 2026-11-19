<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hydrants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('hydrant_code', 50)->unique();
            $table->string('name', 150);
            $table->enum('hydrant_type', [
                'fire_pump_diesel',
                'fire_pump_electric',
                'jockey_pump',
                'hydrant_box',
                'hydrant_pillar',
                'siamese_connection',
            ]);
            $table->string('brand', 100);
            $table->string('model', 100);
            $table->string('serial_number', 100)->nullable();
            $table->date('procurement_date')->nullable();
            $table->date('installation_date')->nullable();
            $table->date('last_maintenance_date')->nullable();
            $table->string('placement', 255);
            $table->uuid('qr_code')->unique();
            $table->string('photo', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('unit_id');
            $table->index('is_active');
            $table->index('hydrant_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hydrants');
    }
};
