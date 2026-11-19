<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class PatrolReportIncidentSheet implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /** @param array<string, mixed> $data */
    public function __construct(private array $data) {}

    public function title(): string
    {
        return 'Laporan Insiden';
    }

    public function headings(): array
    {
        return ['Tanggal/Waktu (WIB)', 'Unit', 'Pos Jaga', 'Regu', 'Titik Patroli', 'Nama Satpam Pelapor', 'Deskripsi Insiden', 'Ketersediaan Foto'];
    }

    public function array(): array
    {
        return collect($this->data['incidents'] ?? [])->map(fn ($r) => [
            $r['scanned_at'] ?? '-',
            $r['unit_name'] ?? '-',
            $r['post_name'] ?? '-',
            $r['team_name'] ?? '-',
            $r['patrol_point_name'] ?? '-',
            $r['security_name'] ?? '-',
            $r['incident_description'] ?? '-',
            $r['photo_label'] ?? 'Tidak ada',
        ])->values()->all();
    }
}
