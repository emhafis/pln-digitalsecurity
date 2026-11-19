<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Concerns\AuthorizesAssetAccess;
use App\Http\Controllers\Controller;
use App\Models\Hydrant;
use App\Models\HydrantInspection;
use App\Models\Security;
use App\Support\AssetInspectionPhotoStorage;
use App\Support\AssetInspectionRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class HydrantController extends Controller
{
    use AuthorizesAssetAccess;

    public function index(Request $request): Response
    {
        $this->authorizeSecurityAccess($request);

        $search = trim((string) $request->query('search', ''));
        $scope = $this->assetUnitScope($request);

        $query = Hydrant::query()
            ->where('is_active', true)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('hydrant_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('placement', 'like', "%{$search}%");
                });
            })
            ->orderBy('hydrant_code');

        $this->scopeAssetsToUnit($query, $scope);

        $paginator = $query->paginate(20)->withQueryString();

        return Inertia::render('App/Hydrant/Index', [
            'items' => $paginator->getCollection()->map(fn (Hydrant $hydrant) => [
                'id' => $hydrant->id,
                'hydrant_code' => $hydrant->hydrant_code,
                'name' => $hydrant->name,
                'hydrant_type' => $hydrant->hydrant_type,
                'hydrant_type_label' => $this->hydrantTypeLabel($hydrant->hydrant_type),
                'brand' => $hydrant->brand,
                'model' => $hydrant->model,
                'placement' => $hydrant->placement,
                'is_active' => (bool) $hydrant->is_active,
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

        $hydrant = $this->findActiveHydrant($request, $id);

        $latestInspection = $hydrant->inspections()
            ->with('security:id,full_name')
            ->latest('inspected_at')
            ->latest('id')
            ->first(['id', 'hydrant_id', 'security_id', 'result', 'inspected_at']);

        return Inertia::render('App/Hydrant/Show', [
            'hydrant' => $this->hydrantDetailPayload($hydrant),
            'latestInspection' => $latestInspection ? [
                'inspected_at' => $latestInspection->inspected_at?->format('d M Y, H:i'),
                'result' => $latestInspection->result,
                'result_label' => $latestInspection->result === 'ok' ? 'Baik' : 'Tidak Baik',
                'officer_name' => $latestInspection->security?->full_name ?? '-',
            ] : null,
        ]);
    }

    public function createInspection(Request $request, int $id): Response
    {
        $this->authorizeSecurityAccess($request);

        $hydrant = $this->findActiveHydrant($request, $id);

        return Inertia::render('App/Hydrant/CreateInspection', [
            'hydrant' => $this->hydrantDetailPayload($hydrant),
            'checklist' => AssetInspectionRules::hydrantChecklist(),
            'maxPhotos' => AssetInspectionRules::MAX_PHOTOS,
        ]);
    }

    public function storeInspection(Request $request, int $id): RedirectResponse
    {
        $this->authorizeSecurityAccess($request);

        $hydrant = $this->findActiveHydrant($request, $id);
        $security = $this->authenticatedSecurity($request);
        $user = $request->user();

        if ($security === null || ! AssetInspectionRules::canSecurityInspectAsset($user, $hydrant)) {
            abort(403);
        }

        $validated = $request->validate(AssetInspectionRules::rules('hydrant'), [
            'items.required' => 'Checklist inspeksi wajib diisi.',
            'items.size' => 'Checklist inspeksi wajib diisi lengkap.',
            'items.*.is_good.required' => 'Status setiap item checklist wajib dipilih.',
            'photos.max' => 'Jumlah foto inspeksi maksimal '.AssetInspectionRules::MAX_PHOTOS.' file.',
            'photos.*.image' => 'File foto inspeksi harus berupa gambar.',
            'photos.*.mimes' => 'Format foto harus jpg, jpeg, png, atau webp.',
            'photos.*.max' => 'Ukuran tiap foto maksimal 2 MB.',
        ]);

        $submittedItems = is_array($validated['items'] ?? null) ? $validated['items'] : [];
        $errors = AssetInspectionRules::validateChecklistCompleteness('hydrant', $submittedItems);
        $normalizedItems = AssetInspectionRules::normalizeItems('hydrant', $submittedItems);
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
            DB::transaction(function () use ($hydrant, $security, $normalizedItems, $result, $notes, $uploaded, &$storedPaths): void {
                $hydrant->refresh();
                if (! $hydrant->is_active || (int) $hydrant->unit_id !== (int) $security->user?->unit_id) {
                    abort(403);
                }

                $inspection = HydrantInspection::query()->create([
                    'hydrant_id' => $hydrant->id,
                    'unit_id' => $hydrant->unit_id,
                    'security_id' => $security->id,
                    'inspected_at' => AssetInspectionRules::serverInspectedAt(),
                    'result' => $result,
                    'notes' => $notes,
                    'unit_name_snapshot' => $hydrant->unit?->unit_name,
                    'unit_code_snapshot' => $hydrant->unit?->unit_code,
                    'hydrant_code_snapshot' => $hydrant->hydrant_code,
                    'name_snapshot' => $hydrant->name,
                    'hydrant_type_snapshot' => $hydrant->hydrant_type,
                    'brand_snapshot' => $hydrant->brand,
                    'model_snapshot' => $hydrant->model,
                    'serial_number_snapshot' => $hydrant->serial_number,
                    'placement_snapshot' => $hydrant->placement,
                    'last_maintenance_date_snapshot' => $hydrant->last_maintenance_date,
                ]);

                foreach ($normalizedItems as $item) {
                    $inspection->items()->create($item);
                }

                foreach ($uploaded as $file) {
                    $path = AssetInspectionPhotoStorage::store($file, 'hydrant', (int) $hydrant->id);
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
            ->route('app.hydrant.show', $hydrant->id)
            ->with('success', 'Inspeksi Hydrant berhasil disimpan.');
    }

    private function findActiveHydrant(Request $request, int $id): Hydrant
    {
        $scope = $this->assetUnitScope($request);

        $hydrant = Hydrant::query()
            ->where('is_active', true)
            ->whereKey($id)
            ->when($scope !== null, fn ($q) => $q->whereIn('unit_id', $scope))
            ->with(['unit'])
            ->first();

        if ($hydrant === null) {
            abort(404);
        }

        return $hydrant;
    }

    /**
     * @return array<string, mixed>
     */
    private function hydrantDetailPayload(Hydrant $hydrant): array
    {
        return [
            'id' => $hydrant->id,
            'hydrant_code' => $hydrant->hydrant_code,
            'name' => $hydrant->name,
            'hydrant_type' => $hydrant->hydrant_type,
            'hydrant_type_label' => $this->hydrantTypeLabel($hydrant->hydrant_type),
            'brand' => $hydrant->brand,
            'model' => $hydrant->model,
            'serial_number' => $hydrant->serial_number,
            'procurement_date' => $hydrant->procurement_date?->format('Y-m-d'),
            'installation_date' => $hydrant->installation_date?->format('Y-m-d'),
            'last_maintenance_date' => $hydrant->last_maintenance_date?->format('Y-m-d'),
            'placement' => $hydrant->placement,
            'is_active' => (bool) $hydrant->is_active,
            'photo_url' => $hydrant->photo ? Storage::disk('public')->url($hydrant->photo) : null,
        ];
    }

    /**
     * Enforce backend access for security Hydrant pages.
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

    private function hydrantTypeLabel(string $type): string
    {
        return match ($type) {
            'fire_pump_diesel' => 'Fire Pump Diesel',
            'fire_pump_electric' => 'Fire Pump Electric',
            'jockey_pump' => 'Jockey Pump',
            'hydrant_box' => 'Hydrant Box',
            'hydrant_pillar' => 'Hydrant Pillar',
            'siamese_connection' => 'Siamese Connection',
            default => $type,
        };
    }
}
