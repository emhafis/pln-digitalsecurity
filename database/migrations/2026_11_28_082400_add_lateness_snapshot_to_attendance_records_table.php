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
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->unsignedSmallInteger('check_in_late_minutes')->nullable()->after('check_in_geofence_valid');
            $table->unsignedSmallInteger('setting_check_in_tolerance_minutes')->nullable()->after('setting_late_tolerance_minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['check_in_late_minutes', 'setting_check_in_tolerance_minutes']);
        });
    }
};
