<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patrol_scans', function (Blueprint $table) {
            $table->string('incident_photo_path')->nullable()->after('incident_description');
        });
    }

    public function down(): void
    {
        Schema::table('patrol_scans', function (Blueprint $table) {
            $table->dropColumn('incident_photo_path');
        });
    }
};
