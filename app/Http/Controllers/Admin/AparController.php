<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesAssetAccess;
use App\Http\Controllers\Controller;
use App\Models\Apar;
use App\Models\Unit;
use App\Support\PublicStorageUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AparController extends Controller
{
    use AuthorizesAssetAccess;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

        $sortColumns = [
            'apar_code' => 'apar_code',
            'apar_type' => 'apar_type',
            'brand' => 'brand',
            'capacity' => 'capacity',
            'placement' => 'placement',
            'dibuat' => 'created_at',
        ];

        $sort = (string) $request->query('sort', 'apar_code');
        if (! array_key_exists($sort, $sortColumns) && $sort !== 'unit') {
            $sort = 'apar_code';
        }
        $dir = $request->query('dir', 'asc') === 'desc' ? 'desc' : 'asc';

        $scope = $this->assetUnitScope($request);
        $unitId = $this->resolveFilterUnitId($request, $scope);

        $query = Apar::query()
            ->with('unit')
            ->withCount('inspections')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('apar_code', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('placement', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false));

        $this->scopeAssetsToUnit($query, $scope);

        if ($unitId !== null) {
            $query->where('unit_id', $unitId);
        }

        if ($sort === 'unit') {
            $query->leftJoin('units', 'apars.unit_id', '=', 'units.id')
                ->orderBy('units.unit_name', $dir)
                ->select('apars.*');
        } else {
            $query->orderBy($sortColumns[$sort], $dir);
        }

        $paginator = $query->paginate(50)->withQueryString();

        return Inertia::render('Admin/Apar/Index', [
            'items' => $paginator->getCollection()->map(fn (Apar $apar) => $this->listItem($apar)),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'search' => $search,
            'status' => $status,
            'sort' => $sort,
            'dir' => $dir,
            'units' => $this->units($scope),
            'unit_id' => $unitId !== null ? (string) $unitId : '',
        ]);
    }

    public function create(Request $request): Response
    {
        $scope = $this->assetUnitScope($request);

        return Inertia::render('Admin/Apar/Create', [
            'units' => $this->units($scope),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $scope = $this->assetUnitScope($request);

        $validated = $request->validate([
            'unit_id' => array_merge($this->unitIdRules($scope), ['required']),
            'apar_type' => ['required', 'string', Rule::in(['dry_chemical', 'co2', 'foam', 'air', 'powder'])],
            'brand' => ['required', 'string', 'max:100'],
            'capacity' => ['required', 'string', 'max:50'],
            'placement' => ['required', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'procurement_date' => ['nullable', 'date'],
            'installation_date' => ['nullable', 'date'],
            'recharge_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $this->authorizeAssetUnit($scope, (int) $validated['unit_id']);

        $isActive = array_key_exists('is_active', $validated)
            ? filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN)
            : true;

        $photoPath = null;

        DB::transaction(function () use ($request, $validated, $isActive, &$photoPath) {
            $apar = Apar::query()->create([
                'unit_id' => (int) $validated['unit_id'],
                'apar_code' => 'TMP-'.Str::uuid()->toString(),
                'apar_type' => $validated['apar_type'],
                'brand' => $validated['brand'],
                'capacity' => $validated['capacity'],
                'placement' => $validated['placement'],
                'serial_number' => $validated['serial_number'] ?? null,
                'procurement_date' => $validated['procurement_date'] ?? null,
                'installation_date' => $validated['installation_date'] ?? null,
                'recharge_date' => $validated['recharge_date'] ?? null,
                'expiry_date' => $validated['expiry_date'] ?? null,
                'qr_code' => Str::uuid()->toString(),
                'is_active' => $isActive,
                'photo' => null,
            ]);

            $apar->apar_code = $this->generateCode($apar->id);

            if ($request->hasFile('photo')) {
                $photoPath = $this->storeAparPhoto($request->file('photo'), $apar->apar_code, $apar->placement);
            }

            $apar->photo = $photoPath;
            $apar->save();
        });

        return redirect()->route('admin.apar.index')->with('success', 'APAR berhasil ditambahkan.');
    }

    public function show(Request $request, int $id): Response
    {
        $apar = Apar::query()->with('unit')->withCount('inspections')->findOrFail($id);
        $this->authorizeAssetRecord($this->assetUnitScope($request), $apar);

        $latestInspection = $apar->inspections()
            ->latest('inspected_at')
            ->latest('id')
            ->first(['id', 'result', 'inspected_at', 'notes']);

        return Inertia::render('Admin/Apar/Show', [
            'apar' => $this->detailItem($apar, $latestInspection),
        ]);
    }

    public function edit(Request $request, int $id): Response
    {
        $apar = Apar::query()->with('unit')->findOrFail($id);
        $scope = $this->assetUnitScope($request);
        $this->authorizeAssetRecord($scope, $apar);

        return Inertia::render('Admin/Apar/Edit', [
            'apar' => $this->editItem($apar),
            'units' => $this->units($scope),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $apar = Apar::query()->findOrFail($id);
        $scope = $this->assetUnitScope($request);
        $this->authorizeAssetRecord($scope, $apar);

        $validated = $request->validate([
            'unit_id' => array_merge($this->unitIdRules($scope), ['required']),
            'apar_type' => ['required', 'string', Rule::in(['dry_chemical', 'co2', 'foam', 'air', 'powder'])],
            'brand' => ['required', 'string', 'max:100'],
            'capacity' => ['required', 'string', 'max:50'],
            'placement' => ['required', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'procurement_date' => ['nullable', 'date'],
            'installation_date' => ['nullable', 'date'],
            'recharge_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],
        ]);

        $this->authorizeAssetUnit($scope, (int) $validated['unit_id']);

        $isActive = array_key_exists('is_active', $validated)
            ? filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN)
            : (bool) $apar->is_active;

        $oldPhoto = $apar->photo;
        $newPhotoPath = $oldPhoto;
        $removePhoto = filter_var($validated['remove_photo'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($request->hasFile('photo')) {
            $newPhotoPath = $this->storeAparPhoto(
                $request->file('photo'),
                $apar->apar_code,
                $validated['placement'] ?? $apar->placement
            );
        } elseif ($removePhoto) {
            $newPhotoPath = null;
        }

        DB::transaction(function () use ($apar, $validated, $isActive, $newPhotoPath) {
            $apar->update([
                'unit_id' => (int) $validated['unit_id'],
                'apar_type' => $validated['apar_type'],
                'brand' => $validated['brand'],
                'capacity' => $validated['capacity'],
                'placement' => $validated['placement'],
                'serial_number' => $validated['serial_number'] ?? null,
                'procurement_date' => $validated['procurement_date'] ?? null,
                'installation_date' => $validated['installation_date'] ?? null,
                'recharge_date' => $validated['recharge_date'] ?? null,
                'expiry_date' => $validated['expiry_date'] ?? null,
                'is_active' => $isActive,
                'photo' => $newPhotoPath,
            ]);
        });

        if (($request->hasFile('photo') || $removePhoto) && $oldPhoto && $oldPhoto !== $newPhotoPath) {
            $this->deleteAparPhoto($oldPhoto);
        }

        return redirect()->route('admin.apar.show', $apar->id)->with('success', 'Data APAR berhasil diperbarui.');
    }

    public function toggleActive(Request $request, int $id): RedirectResponse
    {
        $apar = Apar::query()->findOrFail($id);
        $this->authorizeAssetRecord($this->assetUnitScope($request), $apar);

        $apar->is_active = ! (bool) $apar->is_active;
        $apar->save();

        return back()->with('success', $apar->is_active ? 'APAR diaktifkan.' : 'APAR dinonaktifkan.');
    }

    /**
     * Effective unit filter for list pages (scope-safe).
     *
     * - No `unit_id` query param (first load): scoped users default to their own unit.
     * - Explicit empty param ("Semua Unit"): null => no unit filter (still scope-limited).
     *
     * @param  array<int,int>|null  $scope
     */
    private function resolveFilterUnitId(Request $request, ?array $scope): ?int
    {
        if (! $request->query->has('unit_id')) {
            if ($scope === null) {
                return null;
            }

            $userUnitId = $request->user()?->unit_id;

            return $userUnitId !== null && in_array((int) $userUnitId, $scope, true)
                ? (int) $userUnitId
                : null;
        }

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

    /**
     * @param  array<int,int>|null  $scope
     * @return array<int, string>
     */
    private function unitIdRules(?array $scope): array
    {
        return $scope === null
            ? ['integer', 'exists:units,id']
            : ['integer', Rule::in($scope)];
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
            ])
            ->toArray();
    }

    /**
     * Generate a unique APAR code from the persisted auto-increment id.
     * This avoids max(id)+1 races; any collision with imported codes is
     * resolved by advancing the suffix before the transaction commits.
     */
    private function generateCode(int $id): string
    {
        $sequence = $id;
        do {
            $code = sprintf('APAR-%04d', $sequence);
            $sequence++;
        } while (Apar::query()->where('apar_code', $code)->exists());

        return $code;
    }

    /**
     * Store APAR master photo on public disk and return stored path.
     */
    private function storeAparPhoto($file, ?string $aparCode = null, ?string $placement = null): string
    {
        $ext = \App\Support\UploadFilename::extensionFromUpload($file, 'jpg');
        $filename = \App\Support\UploadFilename::dated([
            'FOTO-APAR',
            \App\Support\UploadFilename::short($aparCode ?? '', 12),
            \App\Support\UploadFilename::short($placement ?? '', 12),
        ], $ext);
        $path = $file->storeAs('apar-photos', $filename, 'public');
        if ($path === false) {
            throw ValidationException::withMessages(['photo' => 'Gagal menyimpan foto APAR.']);
        }

        return $path;
    }

    /**
     * Delete only managed APAR master photos from public disk.
     */
    private function deleteAparPhoto(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }
        $normalized = str_replace('\\', '/', trim($path));
        if (! str_starts_with($normalized, 'apar-photos/')) {
            return;
        }
        if (Storage::disk('public')->exists($normalized)) {
            Storage::disk('public')->delete($normalized);
        }
    }

    private function listItem(Apar $apar): array
    {
        return [
            'id' => $apar->id,
            'apar_code' => $apar->apar_code,
            'apar_type' => $apar->apar_type,
            'apar_type_label' => $this->aparTypeLabel($apar->apar_type),
            'brand' => $apar->brand,
            'capacity' => $apar->capacity,
            'placement' => $apar->placement,
            'unit_name' => $apar->unit?->unit_name ?? '-',
            'is_active' => (bool) $apar->is_active,
            'inspections_count' => (int) ($apar->inspections_count ?? 0),
        ];
    }

    private function detailItem(Apar $apar, $latestInspection): array
    {
        return [
            'id' => $apar->id,
            'apar_code' => $apar->apar_code,
            'apar_type' => $apar->apar_type,
            'apar_type_label' => $this->aparTypeLabel($apar->apar_type),
            'brand' => $apar->brand,
            'capacity' => $apar->capacity,
            'placement' => $apar->placement,
            'serial_number' => $apar->serial_number,
            'procurement_date' => $apar->procurement_date?->format('Y-m-d'),
            'installation_date' => $apar->installation_date?->format('Y-m-d'),
            'recharge_date' => $apar->recharge_date?->format('Y-m-d'),
            'expiry_date' => $apar->expiry_date?->format('Y-m-d'),
            'qr_code' => $apar->qr_code,
            'is_active' => (bool) $apar->is_active,
            'photo_url' => PublicStorageUrl::for($apar->photo),
            'unit_id' => $apar->unit_id,
            'unit_name' => $apar->unit?->unit_name ?? '-',
            'created_at' => $apar->created_at?->format('d M Y, H:i'),
            'updated_at' => $apar->updated_at?->format('d M Y, H:i'),
            'inspections_count' => (int) ($apar->inspections_count ?? 0),
            'latest_inspection' => $latestInspection ? [
                'result' => $latestInspection->result,
                'inspected_at' => $latestInspection->inspected_at?->format('d M Y, H:i'),
            ] : null,
        ];
    }

    private function editItem(Apar $apar): array
    {
        return [
            'id' => $apar->id,
            'unit_id' => $apar->unit_id,
            'apar_code' => $apar->apar_code,
            'apar_type' => $apar->apar_type,
            'brand' => $apar->brand,
            'capacity' => $apar->capacity,
            'placement' => $apar->placement,
            'serial_number' => $apar->serial_number,
            'procurement_date' => $apar->procurement_date?->format('Y-m-d'),
            'installation_date' => $apar->installation_date?->format('Y-m-d'),
            'recharge_date' => $apar->recharge_date?->format('Y-m-d'),
            'expiry_date' => $apar->expiry_date?->format('Y-m-d'),
            'is_active' => (bool) $apar->is_active,
            'photo_url' => PublicStorageUrl::for($apar->photo),
            'qr_code' => $apar->qr_code,
        ];
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
