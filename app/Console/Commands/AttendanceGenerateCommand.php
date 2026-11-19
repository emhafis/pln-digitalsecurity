<?php

namespace App\Console\Commands;

use App\Models\Unit;
use App\Services\AttendanceGenerator;
use Illuminate\Console\Command;

class AttendanceGenerateCommand extends Command
{
    protected $signature = 'attendance:generate
        {--period= : ID periode jadwal yang diproses}
        {--unit= : ID unit (termasuk unit turunannya)}';

    protected $description = 'Membuat atau menyinkronkan data absensi dari jadwal yang siap diproses';

    public function handle(AttendanceGenerator $generator): int
    {
        $periodId = $this->option('period') !== null ? (int) $this->option('period') : null;
        $unitId = $this->option('unit') !== null ? (int) $this->option('unit') : null;

        $unitIds = null;

        if ($unitId !== null) {
            $unit = Unit::query()->find($unitId);

            if ($unit === null) {
                $this->error('Unit tidak ditemukan.');

                return self::FAILURE;
            }

            $unitIds = array_map('intval', $unit->descendantIds());
        }

        $result = $generator->generateAll(null, $unitIds, $periodId);

        if ($result['blocked_by_global']) {
            $this->error('Pembuatan data absensi diblokir karena pengaturan global belum valid.');

            foreach ($result['global_issues'] as $issue) {
                $this->line('- '.$issue['message']);
            }

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Selesai. Dibuat: %d, disinkronkan: %d, terkunci: %d, masalah aktif: %d.',
            $result['created'],
            $result['synced'],
            $result['locked'],
            $result['anomalies']
        ));

        foreach ($result['periods'] as $period) {
            $this->line(sprintf(
                '- %s: %s (dibuat %d, disinkronkan %d, terkunci %d).',
                $period['name'],
                $period['ready'] ? 'siap' : 'belum siap',
                $period['created'],
                $period['synced'],
                $period['locked']
            ));

            foreach ($period['errors'] as $error) {
                $this->warn('  * '.$error);
            }
        }

        return $result['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
