<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class PatrolReportSecuritySheet implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /** @param array<string, mixed> $data */
    public function __construct(private array $data) {}

    public function title(): string
    {
        return 'Aktivitas Satpam';
    }

    public function headings(): array
    {
        return ['Nama Satpam', 'Nomor Registrasi', 'Jumlah Sesi Ditugaskan', 'Jumlah Sesi Diikuti', 'Total Aktivitas Scan', 'Jumlah Insiden Dilaporkan'];
    }

    public function array(): array
    {
        return collect($this->data['security_activity'] ?? [])->map(fn ($r) => [
            $r['security_name'] ?? '-',
            $r['security_registration_number'] ?? '-',
            $r['assigned_sessions'] ?? 0,
            $r['participated_sessions'] ?? 0,
            $r['scan_activity_count'] ?? ($r['scanned_points'] ?? 0),
            $r['incident_reports'] ?? 0,
        ])->values()->all();
    }
}
