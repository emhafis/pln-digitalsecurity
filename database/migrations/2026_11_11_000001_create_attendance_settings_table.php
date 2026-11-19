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
        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('gps_radius_meters')->default(50);
            $table->unsignedSmallInteger('check_in_open_minutes')->default(15);
            $table->unsignedSmallInteger('late_tolerance_minutes')->default(5);
            $table->unsignedSmallInteger('early_checkout_tolerance_minutes')->default(0);
            $table->unsignedSmallInteger('checkout_close_minutes')->default(60);
            $table->unsignedSmallInteger('max_gps_accuracy_meters')->default(50);
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_settings');
    }
};
