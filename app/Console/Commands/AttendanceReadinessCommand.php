<?php

namespace App\Console\Commands;

use App\Models\Unit;
use App\Services\AttendanceReadinessChecker;
use Illuminate\Console\Command;

class AttendanceReadinessCommand extends Command
{
    protected $signature = 'attendance:readiness
        {--period= : ID periode jadwal yang diperiksa}
        {--unit= : ID unit (termasuk unit turunannya)}';

    protected $description = 'Memeriksa kesiapan data absensi';

    public function handle(AttendanceReadinessChecker $checker): int
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

        $report = $checker->check($unitIds, $periodId);

        if (! $report['global']['ready']) {
            $this->error('Pengaturan global belum siap.');

            foreach ($report['global']['issues'] as $issue) {
                $this->line('- '.$issue['message']);
            }
        } else {
            $this->info('Pengaturan global siap.');
        }

        foreach ($report['periods'] as $period) {
            $this->line(sprintf(
                '- %s: %s (%d/%d jadwal siap).',
                $period['name'],
                $period['ready'] ? 'siap' : 'belum siap',
                $period['ready_schedule_count'],
                $period['schedule_count']
            ));

            foreach ($period['issues'] as $issue) {
                $this->warn('  * '.$issue['message']);
            }
        }

        return $report['ready'] ? self::SUCCESS : self::FAILURE;
    }
}
