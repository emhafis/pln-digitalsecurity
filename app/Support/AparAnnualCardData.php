<?php

namespace App\Support;

use App\Models\Apar;
use App\Models\AparInspection;

class AparAnnualCardData
{
    public const MONTH_NAMES = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    public static function resolveYear(mixed $value): int
    {
        $year = (int) $value;
        if ($year < 2000 || $year > 2100) {
            return (int) now()->year;
        }

        return $year;
    }

    /**
     * Build the read-only annual inspection card data for one APAR.
     *
     * A month may contain multiple inspections (possibly by different
     * officers); every stored record is preserved without overwriting.
     *
     * @return array{year:int,months:array<int,array{num:int,name:string}>,inspectionsByMonth:array<int,array<int,array<string,mixed>>>}
     */
    public function build(Apar $apar, int $year, ?int $restrictUnitId = null): array
    {
        $query = AparInspection::query()
            ->where('apar_id', $apar->id)
            ->with([
                'security:id,full_name',
                'items' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
            ])
            ->whereYear('inspected_at', $year);

        if ($restrictUnitId !== null) {
            $query->where('unit_id', $restrictUnitId);
        }

        $inspections = $query
            ->orderBy('inspected_at')
            ->orderBy('id')
            ->get();

        $checklistDefs = AssetInspectionRules::aparChecklist();
        $inspectionsByMonth = [];

        foreach ($inspections as $inspection) {
            $month = (int) $inspection->inspected_at?->format('n');
            if ($month < 1 || $month > 12) {
                continue;
            }

            $itemsByName = $inspection->items->keyBy('item_name');
            $items = [];
            foreach ($checklistDefs as $def) {
                $item = $itemsByName->get($def['item_name']);
                $items[] = [
                    'label' => $def['item_label'],
                    'status' => $item ? ($item->is_good ? 'Baik' : 'Tidak Baik') : '-',
                    'note' => $item && is_string($item->note) && trim($item->note) !== '' ? trim($item->note) : null,
                ];
            }

            $inspectionsByMonth[$month][] = [
                'date_label' => $inspection->inspected_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i'),
                'result_label' => $inspection->result === 'ok' ? 'Baik (OK)' : 'Tidak Baik (NOT OK)',
                'notes' => $inspection->notes,
                'officer' => $inspection->security?->full_name ?? '-',
                'items' => $items,
            ];
        }

        $months = [];
        foreach (self::MONTH_NAMES as $num => $name) {
            $months[] = ['num' => $num, 'name' => $name];
        }

        return [
            'year' => $year,
            'months' => $months,
            'inspectionsByMonth' => $inspectionsByMonth,
        ];
    }
}
