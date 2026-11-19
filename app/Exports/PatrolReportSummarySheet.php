<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class PatrolReportSummarySheet implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /** @param array<string, mixed> $data */
    public function __construct(private array $data) {}

    public function title(): string
    {
        return 'Ringkasan';
    }

    public function headings(): array
    {
        return ['Indikator', 'Nilai'];
    }

    public function array(): array
    {
        $s = $this->data['summary'] ?? [];
        $f = $this->data['filters'] ?? [];

        $statusLabel = $f['status'] ?: 'Semua';

        return [
            ['Tanggal Mulai', $f['date_from'] ?: '-'],
            ['Tanggal Selesai', $f['date_to'] ?: '-'],
            ['Filter Status', $statusLabel],
            ['Total Sesi Patroli', $s['total_sessions'] ?? 0],
            ['Sesi Selesai', $s['completed_sessions'] ?? 0],
            ['Sesi Sedang Berlangsung', $s['active_sessions'] ?? 0],
            ['Sesi Tidak Selesai', $s['incomplete_sessions'] ?? 0],
            ['Sesi Belum Dimulai', $s['not_started_sessions'] ?? 0],
            ['Total Titik Wajib Dipindai', $s['total_required_points'] ?? 0],
            ['Total Titik Terpenuhi Unik', $s['total_fulfilled_points'] ?? ($s['total_scanned_points'] ?? 0)],
            ['Total Aktivitas Scan', $s['total_scan_activity'] ?? 0],
            ['Persentase Penyelesaian (%)', number_format((float) ($s['completion_percentage'] ?? 0), 2, '.', '')],
            ['Total Laporan Insiden', $s['total_incidents'] ?? 0],
        ];
    }
}
