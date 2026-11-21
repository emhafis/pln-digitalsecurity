<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesAssetAccess;
use App\Http\Controllers\Controller;
use App\Models\Apar;
use App\Models\AparInspection;
use App\Models\AparInspectionPhoto;
use App\Models\Unit;
use App\Support\AssetInspectionPhotoStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AparInspectionController extends Controller
{
    use AuthorizesAssetAccess;

    public function index(Request $request, int $apar): Response
    {
        $scope = $this->assetUnitScope($request);
        $aparModel = Apar::query()->with('unit')->findOrFail($apar);
        $this->authorizeAssetRecord($scope, $aparModel);

        $query = AparInspection::query()
            ->where('apar_id', $aparModel->id)
            ->with(['security:id,full_name', 'apar:id,apar_code'])
            ->withCount('photos');

        $this->applyFilters($query, $request);

        $paginator = $query
            ->orderByDesc('inspected_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Apar/Inspections', [
            'apar' => [
                'id' => $aparModel->id,
                'apar_code' => $aparModel->apar_code,
                'unit_name' => $aparModel->unit?->unit_name ?? '-',
                'is_active' => (bool) $aparModel->is_active,
            ],
            'items' => $paginator->getCollection()->map(fn (AparInspection $inspection) => $this->listItem($inspection, true)),
            'meta' => $this->meta($paginator),
            'filters' => $this->filtersPayload($request),
        ]);
    }

    public function monitoring(Request $request): Response
    {
        $scope = $this->assetUnitScope($request);
        $filters = $this->monitoringFilters($request, $scope);

        $query = AparInspection::query()
            ->with(['security:id,full_name', 'apar:id,apar_code,unit_id,is_active'])
            ->withCount('photos')
            ->when($scope !== null, fn ($q) => $q->whereIn('apar_inspections.unit_id', $scope))
            ->when($filters['unit_id'] !== null, fn ($q) => $q->where('apar_inspections.unit_id', $filters['unit_id']))
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $search = $filters['search'];
                $q->where(function ($q) use ($search) {
                    $q->where('apar_code_snapshot', 'like', "%{$search}%")
                        ->orWhere('placement_snapshot', 'like', "%{$search}%")
                        ->orWhere('unit_name_snapshot', 'like', "%{$search}%")
                        ->orWhereHas('security', fn ($sq) => $sq->where('full_name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['result'] !== '', fn ($q) => $q->where('result', $filters['result']))
            ->when($filters['date_from'] !== '', fn ($q) => $q->whereDate('inspected_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== '', fn ($q) => $q->whereDate('inspected_at', '<=', $filters['date_to']));

        $summary = [
            'total' => (clone $query)->count(),
            'ok' => (clone $query)->where('result', 'ok')->count(),
            'not_ok' => (clone $query)->where('result', 'not_ok')->count(),
        ];

        $paginator = $query
            ->orderByDesc('inspected_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Monitoring/Apar/Index', [
            'items' => $paginator->getCollection()->map(fn (AparInspection $inspection) => $this->monitoringListItem($inspection)),
            'meta' => $this->meta($paginator),
            'filters' => $filters,
            'units' => $this->units($scope),
            'summary' => $summary,
        ]);
    }

    public function monitoringShow(Request $request, int $inspection): Response
    {
        $scope = $this->assetUnitScope($request);
        $record = $this->findAuthorizedInspection($inspection, $scope);

        return Inertia::render('Admin/Monitoring/Apar/Show', [
            'inspection' => $this->detailItem($request, $record, 'admin'),
            'back' => [
                'href' => route('admin.monitoring.apar.index'),
                'label' => 'Monitoring APAR',
            ],
        ]);
    }

    public function show(Request $request, int $inspection): Response
    {
        $scope = $this->assetUnitScope($request);
        $record = $this->findAuthorizedInspection($inspection, $scope);

        return Inertia::render('Admin/Apar/InspectionShow', [
            'inspection' => $this->detailItem($request, $record, 'admin'),
            'back' => [
                'href' => route('admin.apar.inspections.index', $record->apar_id),
                'label' => 'Riwayat Inspeksi',
            ],
        ]);
    }

    public function photo(Request $request, int $photo): StreamedResponse
    {
        $scope = $this->assetUnitScope($request);
        $photoModel = AparInspectionPhoto::query()->with('inspection')->findOrFail($photo);
        $inspection = $photoModel->inspection;

        if ($inspection === null || ! $this->isInspectionInScope($scope, $inspection)) {
            abort(404);
        }

        $path = $this->authorizedPhotoPath($photoModel);
        if ($path === null || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return Storage::disk('public')->response($path);
    }

    private function findAuthorizedInspection(int $id, ?array $scope): AparInspection
    {
        $inspection = AparInspection::query()
            ->with([
                'apar:id,apar_code,unit_id,is_active',
                'security:id,full_name',
                'items' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
                'photos' => fn ($q) => $q->orderBy('id'),
            ])
            ->findOrFail($id);

        if (! $this->isInspectionInScope($scope, $inspection)) {
            abort(403);
        }

        return $inspection;
    }

    /**
     * @param array<int,int>|null $scope
     */
    private function isInspectionInScope(?array $scope, AparInspection $inspection): bool
    {
        return $scope === null || in_array((int) $inspection->unit_id, $scope, true);
    }

    private function applyFilters($query, Request $request): void
    {
        $search = trim((string) $request->query('search', ''));
        $result = trim((string) $request->query('result', ''));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

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
    }

    private function filtersPayload(Request $request): array
    {
        $result = trim((string) $request->query('result', ''));
        if (! in_array($result, ['ok', 'not_ok'], true)) {
            $result = '';
        }

        return [
            'search' => trim((string) $request->query('search', '')),
            'result' => $result,
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
        ];
    }

    /**
     * @param  array<int,int>|null  $scope
     * @return array{search:string,result:string,date_from:string,date_to:string,unit_id:?int}
     */
    private function monitoringFilters(Request $request, ?array $scope): array
    {
        $filters = $this->filtersPayload($request);
        $filters['date_from'] = $this->validDate($filters['date_from']);
        $filters['date_to'] = $this->validDate($filters['date_to']);
        $filters['unit_id'] = $this->monitoringUnitId($request, $scope);

        return $filters;
    }

    /**
     * @param  array<int,int>|null  $scope
     */
    private function monitoringUnitId(Request $request, ?array $scope): ?int
    {
        $raw = trim((string) $request->query('unit_id', ''));

        if ($raw === '' || ! is_numeric($raw)) {
            return null;
        }

        $candidate = (int) $raw;
        if ($scope !== null && ! in_array($candidate, $scope, true)) {
            return null;
        }

        return $candidate;
    }

    private function validDate(string $value): string
    {
        if ($value === '') {
            return '';
        }

        try {
            return \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $value)->toDateString();
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * @param  array<int,int>|null  $scope
     * @return array<int, array{value:int,label:string}>
     */
    private function units(?array $scope): array
    {
        return Unit::query()
            ->when($scope !== null, fn ($query) => $query->whereIn('id', $scope))
            ->orderBy('unit_name')
            ->get(['id', 'unit_name'])
            ->map(fn (Unit $unit) => [
                'value' => $unit->id,
                'label' => $unit->unit_name,
            ])->values()->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function listItem(AparInspection $inspection, bool $withLinks): array
    {
        return [
            'id' => $inspection->id,
            'apar_code' => $inspection->apar?->apar_code ?? $inspection->apar_code_snapshot,
            'inspected_at' => $inspection->inspected_at?->format('d M Y, H:i'),
            'inspected_at_raw' => $inspection->inspected_at?->toIso8601String(),
            'officer_name' => $inspection->security?->full_name ?? '-',
            'unit_name' => $inspection->unit_name_snapshot ?: '-',
            'result' => $inspection->result,
            'result_label' => $inspection->result === 'ok' ? 'Baik' : 'Tidak Baik',
            'photos_count' => (int) ($inspection->photos_count ?? 0),
            'detail_url' => $withLinks ? route('admin.apar-inspections.show', $inspection->id) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function monitoringListItem(AparInspection $inspection): array
    {
        return [
            'id' => $inspection->id,
            'apar_id' => $inspection->apar_id,
            'apar_code' => $inspection->apar?->apar_code ?? $inspection->apar_code_snapshot,
            'apar_type' => $inspection->apar_type_snapshot,
            'brand' => $inspection->brand_snapshot,
            'capacity' => $inspection->capacity_snapshot,
            'placement' => $inspection->placement_snapshot,
            'inspected_at' => $inspection->inspected_at?->format('d M Y, H:i'),
            'inspected_at_raw' => $inspection->inspected_at?->toIso8601String(),
            'officer_name' => $inspection->security?->full_name ?? '-',
            'unit_name' => $inspection->unit_name_snapshot ?: '-',
            'result' => $inspection->result,
            'result_label' => $inspection->result === 'ok' ? 'Baik' : 'Tidak Baik',
            'photos_count' => (int) ($inspection->photos_count ?? 0),
            'detail_url' => route('admin.monitoring.apar.show', $inspection->id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailItem(Request $request, AparInspection $inspection, string $area): array
    {
        $photoRoute = $area === 'admin' ? 'admin.apar-inspections.photo' : 'app.apar.inspections.photo';

        return [
            'id' => $inspection->id,
            'apar_id' => $inspection->apar_id,
            'apar_is_active' => (bool) ($inspection->apar?->is_active ?? false),
            'inspected_at' => $inspection->inspected_at?->format('d M Y, H:i'),
            'result' => $inspection->result,
            'result_label' => $inspection->result === 'ok' ? 'Baik' : 'Tidak Baik',
            'notes' => $inspection->notes,
            'unit_name' => $inspection->unit_name_snapshot ?: '-',
            'unit_code' => $inspection->unit_code_snapshot,
            'officer_name' => $inspection->security?->full_name ?? '-',
            'snapshot' => [
                'apar_code' => $inspection->apar_code_snapshot,
                'apar_type' => $inspection->apar_type_snapshot,
                'brand' => $inspection->brand_snapshot,
                'capacity' => $inspection->capacity_snapshot,
                'serial_number' => $inspection->serial_number_snapshot,
                'placement' => $inspection->placement_snapshot,
                'expiry_date' => $inspection->apar_expiry_date_snapshot?->format('Y-m-d'),
            ],
            'items' => $inspection->items->map(fn ($item) => [
                'id' => $item->id,
                'item_name' => $item->item_name,
                'item_label' => $item->item_label,
                'is_good' => (bool) $item->is_good,
                'note' => $item->note,
                'sort_order' => (int) $item->sort_order,
            ])->values(),
            'photos' => $inspection->photos->map(fn ($photo) => [
                'id' => $photo->id,
                'caption' => $photo->caption,
                'url' => '/storage/' . $photo->photo_path,
            ])->values(),
        ];
    }

    private function meta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    private function authorizedPhotoPath(AparInspectionPhoto $photo): ?string
    {
        $path = AssetInspectionPhotoStorage::normalizePath($photo->photo_path);
        if ($path === null || ! AssetInspectionPhotoStorage::isManagedPath($path)) {
            return null;
        }

        return $path;
    }
}
