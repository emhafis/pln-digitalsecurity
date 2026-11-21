<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Concerns\AuthorizesAssetAccess;
use App\Http\Controllers\Controller;
use App\Models\Apar;
use App\Models\AparInspection;
use App\Models\Security;
use App\Support\AssetInspectionPhotoStorage;
use App\Support\AssetInspectionRules;
use App\Support\PublicStorageUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AparController extends Controller
{
    use AuthorizesAssetAccess;

    public function index(Request $request): Response
    {
        $this->authorizeSecurityAccess($request);

        $search = trim((string) $request->query('search', ''));
        $scope = $this->assetUnitScope($request);

        $query = Apar::query()
            ->where('is_active', true)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('apar_code', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('placement', 'like', "%{$search}%");
                });
            })
            ->orderBy('apar_code');

        $this->scopeAssetsToUnit($query, $scope);

        $paginator = $query->paginate(20)->withQueryString();

        return Inertia::render('App/Apar/Index', [
            'items' => $paginator->getCollection()->map(fn (Apar $apar) => [
                'id' => $apar->id,
                'apar_code' => $apar->apar_code,
                'apar_type' => $apar->apar_type,
                'apar_type_label' => $this->aparTypeLabel($apar->apar_type),
                'brand' => $apar->brand,
                'capacity' => $apar->capacity,
                'placement' => $apar->placement,
                'is_active' => (bool) $apar->is_active,
            ]),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'search' => $search,
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $this->authorizeSecurityAccess($request);

        $apar = $this->findInspectableApar($request, $id);

        $latestInspection = $apar->inspections()
            ->with('security:id,full_name')
            ->latest('inspected_at')
            ->latest('id')
            ->first(['id', 'apar_id', 'security_id', 'result', 'inspected_at']);

        return Inertia::render('App/Apar/Show', [
            'apar' => $this->aparDetailPayload($apar),
            'latestInspection' => $latestInspection ? [
                'inspected_at' => $latestInspection->inspected_at?->format('d M Y, H:i'),
                'result' => $latestInspection->result,
                'result_label' => $latestInspection->result === 'ok' ? 'Baik' : 'Tidak Baik',
                'officer_name' => $latestInspection->security?->full_name ?? '-',
            ] : null,
            'canInspect' => true,
        ]);
    }

    public function createInspection(Request $request, int $id): Response
    {
        $this->authorizeSecurityAccess($request);

        $apar = $this->findInspectableApar($request, $id);

        return Inertia::render('App/Apar/CreateInspection', [
            'apar' => $this->aparDetailPayload($apar),
            'checklist' => AssetInspectionRules::aparChecklist(),
            'maxPhotos' => AssetInspectionRules::MAX_PHOTOS,
        ]);
    }

    public function storeInspection(Request $request, int $id): RedirectResponse
    {
        $this->authorizeSecurityAccess($request);

        $apar = $this->findInspectableApar($request, $id);
        $security = $this->authenticatedSecurity($request);
        $user = $request->user();

        if ($security === null || ! AssetInspectionRules::canSecurityInspectAsset($user, $apar)) {
            abort(403);
        }

        $validated = $request->validate(AssetInspectionRules::rules('apar'), [
            'items.required' => 'Checklist inspeksi wajib diisi.',
            'items.size' => 'Checklist inspeksi wajib diisi lengkap.',
            'items.*.is_good.required' => 'Status setiap item checklist wajib dipilih.',
            'photos.max' => 'Jumlah foto inspeksi maksimal '.AssetInspectionRules::MAX_PHOTOS.' file.',
            'photos.*.image' => 'File foto inspeksi harus berupa gambar.',
            'photos.*.mimes' => 'Format foto harus jpg, jpeg, png, atau webp.',
            'photos.*.max' => 'Ukuran tiap foto maksimal 2 MB.',
        ]);

        $submittedItems = is_array($validated['items'] ?? null) ? $validated['items'] : [];
        $errors = AssetInspectionRules::validateChecklistCompleteness('apar', $submittedItems);
        $normalizedItems = AssetInspectionRules::normalizeItems('apar', $submittedItems);
        $result = AssetInspectionRules::resultForItems($normalizedItems);
        $notes = isset($validated['notes']) && is_string($validated['notes']) ? trim($validated['notes']) : null;
        $notes = ($notes === '') ? null : $notes;
        $errors = array_merge($errors, AssetInspectionRules::validateNotesForResult($result, $notes));

        if ($errors !== []) {
            return back()->withErrors(['items' => $errors[0]])->withInput();
        }

        $uploaded = $request->file('photos', []);
        $storedPaths = [];

        try {
            DB::transaction(function () use ($apar, $security, $normalizedItems, $result, $notes, $uploaded, &$storedPaths): void {
                $apar->refresh();
                if (! $apar->is_active || (int) $apar->unit_id !== (int) $security->user?->unit_id) {
                    abort(403);
                }

                $inspection = AparInspection::query()->create([
                    'apar_id' => $apar->id,
                    'unit_id' => $apar->unit_id,
                    'security_id' => $security->id,
                    'inspected_at' => AssetInspectionRules::serverInspectedAt(),
                    'result' => $result,
                    'notes' => $notes,
                    'unit_name_snapshot' => $apar->unit?->unit_name,
                    'unit_code_snapshot' => $apar->unit?->unit_code,
                    'apar_code_snapshot' => $apar->apar_code,
                    'apar_type_snapshot' => $apar->apar_type,
                    'brand_snapshot' => $apar->brand,
                    'capacity_snapshot' => $apar->capacity,
                    'serial_number_snapshot' => $apar->serial_number,
                    'placement_snapshot' => $apar->placement,
                    'apar_expiry_date_snapshot' => $apar->expiry_date,
                ]);

                foreach ($normalizedItems as $item) {
                    $inspection->items()->create($item);
                }

                foreach ($uploaded as $file) {
                    $path = AssetInspectionPhotoStorage::store($file, 'apar', (int) $apar->id, $security->full_name, $apar->apar_code);
                    $storedPaths[] = $path;
                    $inspection->photos()->create([
                        'photo_path' => $path,
                        'caption' => null,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            if ($storedPaths !== []) {
                AssetInspectionPhotoStorage::deleteMany($storedPaths);
            }
            throw $e;
        }

        return redirect()
            ->route('app.apar.show', $apar->id)
            ->with('success', 'Inspeksi APAR berhasil disimpan.');
    }

    private function findInspectableApar(Request $request, int $id): Apar
    {
        $scope = $this->assetUnitScope($request);

        $apar = Apar::query()
            ->where('is_active', true)
            ->whereKey($id)
            ->when($scope !== null, fn ($q) => $q->whereIn('unit_id', $scope))
            ->with(['unit'])
            ->first();

        if ($apar === null) {
            abort(404);
        }

        return $apar;
    }

    /**
     * @return array<string, mixed>
     */
    private function aparDetailPayload(Apar $apar): array
    {
        return [
            'id' => $apar->id,
            'apar_code' => $apar->apar_code,
            'apar_type' => $apar->apar_type,
            'apar_type_label' => $this->aparTypeLabel($apar->apar_type),
            'brand' => $apar->brand,
            'capacity' => $apar->capacity,
            'serial_number' => $apar->serial_number,
            'procurement_date' => $apar->procurement_date?->format('Y-m-d'),
            'expiry_date' => $apar->expiry_date?->format('Y-m-d'),
            'placement' => $apar->placement,
            'is_active' => (bool) $apar->is_active,
            'photo_url' => PublicStorageUrl::for($apar->photo),
        ];
    }

    /**
     * Enforce backend access for security APAR pages.
     * Reject inactive, non-security, missing unit, or missing profile accounts.
     */
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

    private function aparTypeLabel(string $type): string
    {
        return match ($type) {
            'dry_chemical' => 'Dry Chemical',
            'co2' => 'CO2',
            'foam' => 'Foam',
            'air' => 'Air',
            'powder' => 'Powder',
            default => $type,
        };
    }
}
