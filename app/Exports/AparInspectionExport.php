<?php

namespace App\Exports;

use App\Models\AparInspection;
use App\Support\AssetInspectionRules;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class AparInspectionExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithTitle
{
    /**
     * @param  array{apar_id?:int|null,search?:string,result?:string,date_from?:string,date_to?:string,unit_scope?:array<int,int>|null}  $filters
     */
    public function __construct(private array $filters)
    {
    }

    public function title(): string
    {
        return 'Riwayat Inspeksi APAR';
    }

    public function query(): Builder
    {
        $aparId = $this->filters['apar_id'] ?? null;
        $search = trim((string) ($this->filters['search'] ?? ''));
        $result = trim((string) ($this->filters['result'] ?? ''));
        $dateFrom = trim((string) ($this->filters['date_from'] ?? ''));
        $dateTo = trim((string) ($this->filters['date_to'] ?? ''));
        $scope = $this->filters['unit_scope'] ?? null;

        $query = AparInspection::query()
            ->with([
                'apar:id,apar_code',
                'security:id,full_name',
                'items' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
            ]);

        if ($aparId !== null) {
            $query->where('apar_id', (int) $aparId);
        }

        if ($scope !== null) {
            $query->whereIn('unit_id', $scope);
        }

        $query->when($search !== '', function ($q) use ($search) {
            $q->where(function ($q) use ($search) {
                $q->whereHas('apar', fn ($aq) => $aq->where('apar_code', 'like', "%{$search}%"))
                    ->orWhere('apar_code_snapshot', 'like', "%{$search}%")
                    ->orWhereHas('security', fn ($sq) => $sq->where('full_name', 'like', "%{$search}%"));
            });
        });

        if (in_array($result, ['ok', 'not_ok'], true)) {
            $query->where('result', $result);
        }
        if ($dateFrom !== '') {
            $query->whereDate('inspected_at', '>=', $dateFrom);
        }
        if ($dateTo !== '') {
            $query->whereDate('inspected_at', '<=', $dateTo);
        }

        return $query->orderByDesc('inspected_at')->orderByDesc('id');
    }

    public function headings(): array
    {
        $headings = [
            'ID Inspeksi',
            'Kode APAR (Snapshot)',
            'Jenis APAR (Snapshot)',
            'Merek (Snapshot)',
            'Kapasitas (Snapshot)',
            'Nomor Seri (Snapshot)',
            'Lokasi (Snapshot)',
            'Kedaluwarsa (Snapshot)',
            'Unit (Snapshot)',
            'Kode Unit (Snapshot)',
            'Petugas',
            'Waktu Inspeksi',
            'Hasil',
            'Catatan',
        ];

        foreach (AssetInspectionRules::aparChecklist() as $item) {
            $headings[] = $item['item_label'];
        }

        return $headings;
    }

    /**
     * @param  AparInspection  $inspection
     */
    public function map($inspection): array
    {
        $row = [
            $inspection->id,
            $inspection->apar_code_snapshot ?? $inspection->apar?->apar_code,
            $inspection->apar_type_snapshot,
            $inspection->brand_snapshot,
            $inspection->capacity_snapshot,
            $inspection->serial_number_snapshot,
            $inspection->placement_snapshot,
            $inspection->apar_expiry_date_snapshot?->format('Y-m-d'),
            $inspection->unit_name_snapshot,
            $inspection->unit_code_snapshot,
            $inspection->security?->full_name ?? '-',
            $inspection->inspected_at?->format('Y-m-d H:i:s'),
            $inspection->result === 'ok' ? 'Baik (OK)' : 'Tidak Baik (NOT OK)',
            $inspection->notes,
        ];

        $itemsByName = $inspection->items->keyBy('item_name');
        foreach (AssetInspectionRules::aparChecklist() as $def) {
            $item = $itemsByName->get($def['item_name']);
            if ($item === null) {
                $row[] = '-';
                continue;
            }
            $status = $item->is_good ? 'Baik' : 'Tidak Baik';
            $note = is_string($item->note) && trim($item->note) !== '' ? trim($item->note) : null;
            $row[] = $note ? ($status.' — '.$note) : $status;
        }

        return $row;
    }
}
