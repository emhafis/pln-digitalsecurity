<?php

namespace App\Exports;

use App\Models\HydrantInspection;
use App\Support\AssetInspectionRules;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class HydrantInspectionExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithTitle
{
    /**
     * @param  array{hydrant_id?:int|null,search?:string,result?:string,date_from?:string,date_to?:string,unit_scope?:array<int,int>|null}  $filters
     */
    public function __construct(private array $filters)
    {
    }

    public function title(): string
    {
        return 'Riwayat Inspeksi Hydrant';
    }

    public function query(): Builder
    {
        $hydrantId = $this->filters['hydrant_id'] ?? null;
        $search = trim((string) ($this->filters['search'] ?? ''));
        $result = trim((string) ($this->filters['result'] ?? ''));
        $dateFrom = trim((string) ($this->filters['date_from'] ?? ''));
        $dateTo = trim((string) ($this->filters['date_to'] ?? ''));
        $scope = $this->filters['unit_scope'] ?? null;

        $query = HydrantInspection::query()
            ->with([
                'hydrant:id,hydrant_code',
                'security:id,full_name',
                'items' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
            ]);

        if ($hydrantId !== null) {
            $query->where('hydrant_id', (int) $hydrantId);
        }

        if ($scope !== null) {
            $query->whereIn('unit_id', $scope);
        }

        $query->when($search !== '', function ($q) use ($search) {
            $q->where(function ($q) use ($search) {
                $q->whereHas('hydrant', fn ($hq) => $hq->where('hydrant_code', 'like', "%{$search}%"))
                    ->orWhere('hydrant_code_snapshot', 'like', "%{$search}%")
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
            'Kode Hydrant (Snapshot)',
            'Nama (Snapshot)',
            'Jenis (Snapshot)',
            'Merek (Snapshot)',
            'Model (Snapshot)',
            'Nomor Seri (Snapshot)',
            'Lokasi (Snapshot)',
            'Maintenance Terakhir (Snapshot)',
            'Unit (Snapshot)',
            'Kode Unit (Snapshot)',
            'Petugas',
            'Waktu Inspeksi',
            'Hasil',
            'Catatan',
        ];

        foreach (AssetInspectionRules::hydrantChecklist() as $item) {
            $headings[] = $item['item_label'];
        }

        return $headings;
    }

    /**
     * @param  HydrantInspection  $inspection
     */
    public function map($inspection): array
    {
        $row = [
            $inspection->id,
            $inspection->hydrant_code_snapshot ?? $inspection->hydrant?->hydrant_code,
            $inspection->name_snapshot,
            $inspection->hydrant_type_snapshot,
            $inspection->brand_snapshot,
            $inspection->model_snapshot,
            $inspection->serial_number_snapshot,
            $inspection->placement_snapshot,
            $inspection->last_maintenance_date_snapshot?->format('Y-m-d'),
            $inspection->unit_name_snapshot,
            $inspection->unit_code_snapshot,
            $inspection->security?->full_name ?? '-',
            $inspection->inspected_at?->format('Y-m-d H:i:s'),
            $inspection->result === 'ok' ? 'Baik (OK)' : 'Tidak Baik (NOT OK)',
            $inspection->notes,
        ];

        $itemsByName = $inspection->items->keyBy('item_name');
        foreach (AssetInspectionRules::hydrantChecklist() as $def) {
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
