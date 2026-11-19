<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patrol_scans', function (Blueprint $table) {
            // Pastikan kolom FK tetap terindeks agar unique komposit lama dapat dilepas (MySQL/InnoDB).
            $table->index('patrol_session_id', 'patrol_scans_session_idx');
            $table->index('patrol_point_id', 'patrol_scans_point_idx');
            $table->dropUnique('patrol_scans_session_point_unique');
            $table->string('idempotency_key', 64)->nullable()->after('incident_photo_path');
            $table->unique(['patrol_session_id', 'idempotency_key'], 'patrol_scans_session_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('patrol_scans', function (Blueprint $table) {
            $table->dropUnique('patrol_scans_session_idempotency_unique');
            $table->dropColumn('idempotency_key');
            $table->unique(['patrol_session_id', 'patrol_point_id'], 'patrol_scans_session_point_unique');
            $table->dropIndex('patrol_scans_session_idx');
            $table->dropIndex('patrol_scans_point_idx');
        });
    }
};
