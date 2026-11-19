<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class PatrolReportUnitSheet implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /** @param array<string, mixed> $data */
    public function __construct(private array $data) {}

    public function title(): string
    {
        return 'Rekap Unit';
    }

    public function headings(): array
    {
        return ['Nama Unit', 'Total Sesi', 'Sesi Selesai', 'Sesi Belum Selesai', 'Total Titik Wajib', 'Titik Terpenuhi Unik', 'Total Aktivitas Scan', 'Persentase Penyelesaian (%)', 'Total Insiden'];
    }

    public function array(): array
    {
        return collect($this->data['by_unit'] ?? [])->map(fn ($r) => [
            $r['unit_name'] ?? '-',
            $r['total_sessions'] ?? 0,
            $r['completed_sessions'] ?? 0,
            $r['not_completed_sessions'] ?? 0,
            $r['required_points'] ?? 0,
            $r['fulfilled_points'] ?? ($r['scanned_points'] ?? 0),
            $r['scan_activity_count'] ?? 0,
            number_format((float) ($r['completion_percentage'] ?? 0), 2, '.', ''),
            $r['incidents'] ?? 0,
        ])->values()->all();
    }
}
