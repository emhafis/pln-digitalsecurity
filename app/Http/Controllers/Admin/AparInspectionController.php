<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesAssetAccess;
use App\Http\Controllers\Controller;
use App\Models\Apar;
use App\Models\AparInspection;
use App\Models\AparInspectionPhoto;
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
        if ($path === null || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return Storage::disk('local')->response($path);
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
                'url' => route($photoRoute, $photo->id),
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
