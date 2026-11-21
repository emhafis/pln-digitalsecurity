<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Concerns\AuthorizesAssetAccess;
use App\Http\Controllers\Controller;
use App\Models\Apar;
use App\Models\AparInspection;
use App\Models\AparInspectionPhoto;
use App\Models\Security;
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
        $this->authorizeSecurityAccess($request);

        $aparModel = $this->findOwnedApar($request, $apar);

        $query = AparInspection::query()
            ->where('apar_id', $aparModel->id)
            ->where('unit_id', (int) $request->user()->unit_id)
            ->with(['security:id,full_name', 'apar:id,apar_code'])
            ->withCount('photos');

        $this->applyFilters($query, $request);

        $paginator = $query
            ->orderByDesc('inspected_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('App/Apar/Inspections', [
            'apar' => [
                'id' => $aparModel->id,
                'apar_code' => $aparModel->apar_code,
                'is_active' => (bool) $aparModel->is_active,
            ],
            'items' => $paginator->getCollection()->map(fn (AparInspection $inspection) => [
                'id' => $inspection->id,
                'apar_code' => $inspection->apar?->apar_code ?? $inspection->apar_code_snapshot,
                'inspected_at' => $inspection->inspected_at?->format('d M Y, H:i'),
                'officer_name' => $inspection->security?->full_name ?? '-',
                'unit_name' => $inspection->unit_name_snapshot ?: '-',
                'result' => $inspection->result,
                'result_label' => $inspection->result === 'ok' ? 'Baik' : 'Tidak Baik',
                'photos_count' => (int) ($inspection->photos_count ?? 0),
                'detail_url' => route('app.apar.inspections.show', $inspection->id),
            ]),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'filters' => $this->filtersPayload($request),
        ]);
    }

    public function show(Request $request, int $inspection): Response
    {
        $this->authorizeSecurityAccess($request);

        $record = $this->findOwnedInspection($request, $inspection);

        return Inertia::render('App/Apar/InspectionShow', [
            'inspection' => $this->detailItem($record),
            'back' => [
                'href' => route('app.apar.inspections.index', $record->apar_id),
                'label' => 'Riwayat Inspeksi',
            ],
        ]);
    }

    public function photo(Request $request, int $photo): StreamedResponse
    {
        $this->authorizeSecurityAccess($request);

        $photoModel = AparInspectionPhoto::query()->with('inspection')->findOrFail($photo);
        $inspection = $photoModel->inspection;
        $userUnitId = (int) $request->user()->unit_id;

        if ($inspection === null || (int) $inspection->unit_id !== $userUnitId) {
            // Do not leak existence across units
            abort(404);
        }

        $path = AssetInspectionPhotoStorage::normalizePath($photoModel->photo_path);
        if ($path === null || ! AssetInspectionPhotoStorage::isManagedPath($path) || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return Storage::disk('public')->response($path);
    }

    private function findOwnedApar(Request $request, int $id): Apar
    {
        $scope = $this->assetUnitScope($request);
        $apar = Apar::query()
            ->whereKey($id)
            ->when($scope !== null, fn ($q) => $q->whereIn('unit_id', $scope))
            ->with('unit')
            ->first();

        if ($apar === null) {
            abort(404);
        }

        return $apar;
    }

    private function findOwnedInspection(Request $request, int $id): AparInspection
    {
        $userUnitId = (int) $request->user()->unit_id;
        $inspection = AparInspection::query()
            ->whereKey($id)
            ->where('unit_id', $userUnitId)
            ->with([
                'apar:id,apar_code,unit_id,is_active',
                'security:id,full_name',
                'items' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
                'photos' => fn ($q) => $q->orderBy('id'),
            ])
            ->first();

        if ($inspection === null) {
            abort(404);
        }

        return $inspection;
    }

    private function applyFilters($query, Request $request): void
    {
        $search = trim((string) $request->query('search', ''));
        $result = trim((string) $request->query('result', ''));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        $query->when($search !== '', function ($q) use ($search) {
            $q->where(function ($q) use ($search) {
                $q->where('apar_code_snapshot', 'like', "%{$search}%")
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

    private function detailItem(AparInspection $inspection): array
    {
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

    private function authorizeSecurityAccess(Request $request): void
    {
        $user = $request->user();
        if ($user === null || $user->role !== 'security' || ! $user->is_active || $user->unit_id === null) {
            abort(403);
        }

        $hasProfile = Security::query()->where('user_id', $user->id)->exists();
        if (! $hasProfile) {
            abort(403);
        }
    }
}
