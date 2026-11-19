<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesAssetAccess;
use App\Http\Controllers\Controller;
use App\Models\Hydrant;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class HydrantController extends Controller
{
    use AuthorizesAssetAccess;

    private const TYPES = [
        'fire_pump_diesel',
        'fire_pump_electric',
        'jockey_pump',
        'hydrant_box',
        'hydrant_pillar',
        'siamese_connection',
    ];

    /**
     * MVP restriction: only fire pump diesel can be created through the form.
     * Other enum values remain in the schema for future development.
     */
    private const MVP_TYPES = ['fire_pump_diesel'];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

        $sortColumns = [
            'hydrant_code' => 'hydrant_code',
            'name' => 'name',
            'hydrant_type' => 'hydrant_type',
            'brand' => 'brand',
            'model' => 'model',
            'placement' => 'placement',
            'dibuat' => 'created_at',
        ];

        $sort = (string) $request->query('sort', 'hydrant_code');
        if (! array_key_exists($sort, $sortColumns) && $sort !== 'unit') {
            $sort = 'hydrant_code';
        }
        $dir = $request->query('dir', 'asc') === 'desc' ? 'desc' : 'asc';

        $scope = $this->assetUnitScope($request);
        $unitId = $this->resolveFilterUnitId($request, $scope);

        $query = Hydrant::query()
            ->with('unit')
            ->withCount('inspections')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('hydrant_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
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
            $query->leftJoin('units', 'hydrants.unit_id', '=', 'units.id')
                ->orderBy('units.unit_name', $dir)
                ->select('hydrants.*');
        } else {
            $query->orderBy($sortColumns[$sort], $dir);
        }

        $paginator = $query->paginate(50)->withQueryString();

        return Inertia::render('Admin/Hydrant/Index', [
            'items' => $paginator->getCollection()->map(fn (Hydrant $hydrant) => $this->listItem($hydrant)),
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

        return Inertia::render('Admin/Hydrant/Create', [
            'units' => $this->units($scope),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $scope = $this->assetUnitScope($request);

        $validated = $request->validate(array_merge($this->rules($scope), [
            'hydrant_type' => ['required', 'string', Rule::in(self::MVP_TYPES)],
        ]));

        $this->authorizeAssetUnit($scope, (int) $validated['unit_id']);

        $isActive = array_key_exists('is_active', $validated)
            ? filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN)
            : true;

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $this->storeHydrantPhoto($request->file('photo'));
        }

        DB::transaction(function () use ($validated, $isActive, &$photoPath) {
            $hydrant = Hydrant::query()->create([
                'unit_id' => (int) $validated['unit_id'],
                'hydrant_code' => 'TMP-'.Str::uuid()->toString(),
                'name' => $validated['name'],
                'hydrant_type' => $validated['hydrant_type'],
                'brand' => $validated['brand'],
                'model' => $validated['model'],
                'serial_number' => $validated['serial_number'] ?? null,
                'procurement_date' => $validated['procurement_date'] ?? null,
                'installation_date' => $validated['installation_date'] ?? null,
                'last_maintenance_date' => $validated['last_maintenance_date'] ?? null,
                'placement' => $validated['placement'],
                'qr_code' => Str::uuid()->toString(),
                'is_active' => $isActive,
                'photo' => $photoPath,
            ]);

            $hydrant->hydrant_code = $this->generateCode($hydrant->id);

            $currentPhoto = $photoPath;
            if ($currentPhoto !== null && is_string($currentPhoto) && $currentPhoto !== '' && str_starts_with(str_replace('\\', '/', $currentPhoto), 'hydrant-photos/')) {
                $dir = pathinfo($currentPhoto, PATHINFO_DIRNAME);
                $ext = pathinfo($currentPhoto, PATHINFO_EXTENSION) ?: 'jpg';
                $newName = \App\Support\UploadFilename::make(['FOTO-HYDRANT', $hydrant->hydrant_code, $validated['name'] ?? $hydrant->name], $ext);
                $newPath = ($dir === '.' ? 'hydrant-photos' : str_replace('\\', '/', $dir)).'/'.$newName;
                if ($newPath !== $currentPhoto && Storage::disk('public')->exists($currentPhoto)) {
                    if (Storage::disk('public')->move($currentPhoto, $newPath)) {
                        $currentPhoto = $newPath;
                    }
                }
            }
            $photoPath = $currentPhoto;
            $hydrant->photo = $photoPath;
            $hydrant->save();
        });

        return redirect()->route('admin.hydrant.index')->with('success', 'Hydrant berhasil ditambahkan.');
    }

    public function show(Request $request, int $id): Response
    {
        $hydrant = Hydrant::query()->with('unit')->withCount('inspections')->findOrFail($id);
        $this->authorizeAssetRecord($this->assetUnitScope($request), $hydrant);

        $latestInspection = $hydrant->inspections()
            ->latest('inspected_at')
            ->latest('id')
            ->first(['id', 'result', 'inspected_at', 'notes']);

        return Inertia::render('Admin/Hydrant/Show', [
            'hydrant' => $this->detailItem($hydrant, $latestInspection),
        ]);
    }

    public function edit(Request $request, int $id): Response
    {
        $hydrant = Hydrant::query()->with('unit')->findOrFail($id);
        $scope = $this->assetUnitScope($request);
        $this->authorizeAssetRecord($scope, $hydrant);

        return Inertia::render('Admin/Hydrant/Edit', [
            'hydrant' => $this->editItem($hydrant),
            'units' => $this->units($scope),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $hydrant = Hydrant::query()->findOrFail($id);
        $scope = $this->assetUnitScope($request);
        $this->authorizeAssetRecord($scope, $hydrant);

        $validated = $request->validate($this->rules($scope));

        $this->authorizeAssetUnit($scope, (int) $validated['unit_id']);

        $isActive = array_key_exists('is_active', $validated)
            ? filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN)
            : (bool) $hydrant->is_active;

        $oldPhoto = $hydrant->photo;
        $newPhotoPath = $oldPhoto;
        $removePhoto = filter_var($validated['remove_photo'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($request->hasFile('photo')) {
            $ext = \App\Support\UploadFilename::extensionFromUpload($request->file('photo'), 'jpg');
            $name = \App\Support\UploadFilename::make(['FOTO-HYDRANT', $hydrant->hydrant_code, $validated['name'] ?? $hydrant->name], $ext);
            $stored = $request->file('photo')->storeAs('hydrant-photos', $name, 'public');
            if ($stored === false) {
                throw ValidationException::withMessages(['photo' => 'Gagal menyimpan foto Hydrant.']);
            }
            $newPhotoPath = $stored;
        } elseif ($removePhoto) {
            $newPhotoPath = null;
        }

        DB::transaction(function () use ($hydrant, $validated, $isActive, $newPhotoPath) {
            $hydrant->update([
                'unit_id' => (int) $validated['unit_id'],
                'name' => $validated['name'],
                'brand' => $validated['brand'],
                'model' => $validated['model'],
                'serial_number' => $validated['serial_number'] ?? null,
                'procurement_date' => $validated['procurement_date'] ?? null,
                'installation_date' => $validated['installation_date'] ?? null,
                'last_maintenance_date' => $validated['last_maintenance_date'] ?? null,
                'placement' => $validated['placement'],
                'is_active' => $isActive,
                'photo' => $newPhotoPath,
            ]);
        });

        if (($request->hasFile('photo') || $removePhoto) && $oldPhoto && $oldPhoto !== $newPhotoPath) {
            $this->deleteHydrantPhoto($oldPhoto);
        }

        return redirect()->route('admin.hydrant.show', $hydrant->id)->with('success', 'Data Hydrant berhasil diperbarui.');
    }

    public function toggleActive(Request $request, int $id): RedirectResponse
    {
        $hydrant = Hydrant::query()->findOrFail($id);
        $this->authorizeAssetRecord($this->assetUnitScope($request), $hydrant);

        $hydrant->is_active = ! (bool) $hydrant->is_active;
        $hydrant->save();

        return back()->with('success', $hydrant->is_active ? 'Hydrant diaktifkan.' : 'Hydrant dinonaktifkan.');
    }

    /**
     * @param  array<int,int>|null  $scope
     * @return array<string,mixed>
     */
    private function rules(?array $scope): array
    {
        return [
            'unit_id' => array_merge($this->unitIdRules($scope), ['required']),
            'name' => ['required', 'string', 'max:150'],
            'brand' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'procurement_date' => ['nullable', 'date'],
            'installation_date' => ['nullable', 'date'],
            'last_maintenance_date' => ['nullable', 'date'],
            'placement' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Effective unit filter for list pages (scope-safe).
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
     * Generate a unique Hydrant code from the persisted auto-increment id.
     * This avoids max(id)+1 races; any collision with imported codes is
     * resolved by advancing the suffix before the transaction commits.
     */
    private function generateCode(int $id): string
    {
        $sequence = $id;
        do {
            $code = sprintf('HYD-%04d', $sequence);
            $sequence++;
        } while (Hydrant::query()->where('hydrant_code', $code)->exists());

        return $code;
    }

    /**
     * Store Hydrant master photo on public disk and return stored path.
     */
    private function storeHydrantPhoto($file): string
    {
        $ext = \App\Support\UploadFilename::extensionFromUpload($file, 'jpg');
        $filename = \App\Support\UploadFilename::make(['FOTO-HYDRANT'], $ext);
        $path = $file->storeAs('hydrant-photos', $filename, 'public');
        if ($path === false) {
            throw ValidationException::withMessages(['photo' => 'Gagal menyimpan foto Hydrant.']);
        }

        return $path;
    }

    /**
     * Delete only managed Hydrant master photos from public disk.
     */
    private function deleteHydrantPhoto(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }
        $normalized = str_replace('\\', '/', trim($path));
        if (! str_starts_with($normalized, 'hydrant-photos/')) {
            return;
        }
        if (Storage::disk('public')->exists($normalized)) {
            Storage::disk('public')->delete($normalized);
        }
    }

    private function listItem(Hydrant $hydrant): array
    {
        return [
            'id' => $hydrant->id,
            'hydrant_code' => $hydrant->hydrant_code,
            'name' => $hydrant->name,
            'hydrant_type' => $hydrant->hydrant_type,
            'hydrant_type_label' => $this->hydrantTypeLabel($hydrant->hydrant_type),
            'brand' => $hydrant->brand,
            'model' => $hydrant->model,
            'placement' => $hydrant->placement,
            'unit_name' => $hydrant->unit?->unit_name ?? '-',
            'is_active' => (bool) $hydrant->is_active,
            'inspections_count' => (int) ($hydrant->inspections_count ?? 0),
        ];
    }

    private function detailItem(Hydrant $hydrant, $latestInspection): array
    {
        return [
            'id' => $hydrant->id,
            'hydrant_code' => $hydrant->hydrant_code,
            'name' => $hydrant->name,
            'hydrant_type' => $hydrant->hydrant_type,
            'hydrant_type_label' => $this->hydrantTypeLabel($hydrant->hydrant_type),
            'brand' => $hydrant->brand,
            'model' => $hydrant->model,
            'placement' => $hydrant->placement,
            'serial_number' => $hydrant->serial_number,
            'procurement_date' => $hydrant->procurement_date?->format('Y-m-d'),
            'installation_date' => $hydrant->installation_date?->format('Y-m-d'),
            'last_maintenance_date' => $hydrant->last_maintenance_date?->format('Y-m-d'),
            'qr_code' => $hydrant->qr_code,
            'is_active' => (bool) $hydrant->is_active,
            'photo_url' => $hydrant->photo ? Storage::disk('public')->url($hydrant->photo) : null,
            'unit_id' => $hydrant->unit_id,
            'unit_name' => $hydrant->unit?->unit_name ?? '-',
            'created_at' => $hydrant->created_at?->format('d M Y, H:i'),
            'updated_at' => $hydrant->updated_at?->format('d M Y, H:i'),
            'inspections_count' => (int) ($hydrant->inspections_count ?? 0),
            'latest_inspection' => $latestInspection ? [
                'result' => $latestInspection->result,
                'inspected_at' => $latestInspection->inspected_at?->format('d M Y, H:i'),
            ] : null,
        ];
    }

    private function editItem(Hydrant $hydrant): array
    {
        return [
            'id' => $hydrant->id,
            'unit_id' => $hydrant->unit_id,
            'hydrant_code' => $hydrant->hydrant_code,
            'name' => $hydrant->name,
            'hydrant_type' => $hydrant->hydrant_type,
            'brand' => $hydrant->brand,
            'model' => $hydrant->model,
            'placement' => $hydrant->placement,
            'serial_number' => $hydrant->serial_number,
            'procurement_date' => $hydrant->procurement_date?->format('Y-m-d'),
            'installation_date' => $hydrant->installation_date?->format('Y-m-d'),
            'last_maintenance_date' => $hydrant->last_maintenance_date?->format('Y-m-d'),
            'is_active' => (bool) $hydrant->is_active,
            'photo_url' => $hydrant->photo ? Storage::disk('public')->url($hydrant->photo) : null,
            'qr_code' => $hydrant->qr_code,
        ];
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
