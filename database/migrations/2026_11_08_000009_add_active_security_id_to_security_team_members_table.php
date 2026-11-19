<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $duplicates = DB::table('security_team_members')
            ->select('security_id', DB::raw('COUNT(*) as active_count'))
            ->whereNull('left_at')
            ->groupBy('security_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $securityIds = $duplicates->pluck('security_id')->map(fn ($id) => (int) $id)->all();

            $rows = DB::table('security_team_members')
                ->whereIn('security_id', $securityIds)
                ->whereNull('left_at')
                ->orderBy('security_id')
                ->orderBy('id')
                ->get(['id', 'security_id', 'security_team_id', 'joined_at']);

            $report = $rows
                ->map(fn ($row) => sprintf(
                    '- membership #%d: security_id=%d, security_team_id=%d, joined_at=%s',
                    $row->id,
                    $row->security_id,
                    $row->security_team_id,
                    $row->joined_at
                ))
                ->implode(PHP_EOL);

            throw new RuntimeException(
                'Migrasi dihentikan: ditemukan satpam dengan lebih dari satu keanggotaan regu aktif '
                .'(left_at NULL). Bersihkan data secara manual terlebih dahulu, tanpa menghapus riwayat. '
                .'Daftar data bermasalah:'.PHP_EOL.$report
            );
        }

        Schema::table('security_team_members', function (Blueprint $table) {
            $table->unsignedBigInteger('active_security_id')->nullable();
        });

        DB::table('security_team_members')
            ->whereNull('left_at')
            ->update(['active_security_id' => DB::raw('security_id')]);

        Schema::table('security_team_members', function (Blueprint $table) {
            $table->unique(
                'active_security_id',
                'security_team_members_active_security_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('security_team_members', function (Blueprint $table) {
            $table->dropUnique('security_team_members_active_security_unique');
            $table->dropColumn('active_security_id');
        });
    }
};
