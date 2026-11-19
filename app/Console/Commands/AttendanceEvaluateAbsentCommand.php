<?php

namespace App\Console\Commands;

use App\Services\AttendanceStatusEvaluator;
use Illuminate\Console\Command;

class AttendanceEvaluateAbsentCommand extends Command
{
    protected $signature = 'attendance:evaluate-absent';

    protected $description = 'Menetapkan status Tidak Hadir untuk absensi yang melewati batas check-in';

    public function handle(AttendanceStatusEvaluator $evaluator): int
    {
        $count = $evaluator->markAbsent();

        $this->info("Selesai. {$count} absensi ditetapkan Tidak Hadir.");

        return self::SUCCESS;
    }
}
