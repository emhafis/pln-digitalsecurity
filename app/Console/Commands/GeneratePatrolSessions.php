<?php

namespace App\Console\Commands;

use App\Services\PatrolSessionGenerator;
use Illuminate\Console\Command;

class GeneratePatrolSessions extends Command
{
    protected $signature = 'patrol:generate-sessions';

    protected $description = 'Membentuk sesi patroli dari jadwal periode aktif';

    public function handle(PatrolSessionGenerator $generator): int
    {
        $summary = $generator->generate();

        $this->info('Selesai generate sesi patroli.');
        $this->line('- Periode aktif diperiksa: '.$summary['active_periods']);
        $this->line('- Kelompok jadwal eligible: '.$summary['eligible_groups']);
        $this->line('- Sesi baru dibuat: '.$summary['sessions_created']);
        $this->line('- Kelompok dilewati: '.$summary['groups_skipped']);
        $this->line('- Sesi sudah tersedia: '.$summary['sessions_existing']);
        $this->line('- Anomali: '.$summary['anomalies']);
        $this->line('- Error: '.$summary['errors']);

        return $summary['errors'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
