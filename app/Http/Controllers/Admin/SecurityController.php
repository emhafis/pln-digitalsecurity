<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Security;
use App\Models\Unit;
use App\Models\User;
use App\Support\PublicStorageUrl;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController extends Controller
{
    /**
     * Security personnel list.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $sortColumns = [
            'registration_number' => 'securities.registration_number',
            'full_name' => 'securities.full_name',
            'phone_number' => 'securities.phone_number',
            'kta_number' => 'securities.kta_number',
            'dibuat' => 'securities.created_at',
        ];

        $sort = (string) $request->query('sort', 'full_name');
        if (! array_key_exists($sort, $sortColumns) && $sort !== 'unit') {
            $sort = 'full_name';
        }
        $dir = $request->query('dir', 'asc') === 'desc' ? 'desc' : 'asc';

        $query = Security::query()
            ->with('user.unit')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('securities.registration_number', 'like', "%{$search}%")
                        ->orWhere('securities.full_name', 'like', "%{$search}%")
                        ->orWhere('securities.phone_number', 'like', "%{$search}%")
                        ->orWhere('securities.kta_number', 'like', "%{$search}%");
                });
            });

        $scope = $this->unitScope($request);
        if ($scope !== null) {
            $query->whereHas('user', fn ($query) => $query->whereIn('unit_id', $scope));
        }

        $filterUnitId = $this->resolveFilterUnitId($request, $scope);
        if ($filterUnitId !== null) {
            $query->whereHas('user', fn ($query) => $query->where('unit_id', $filterUnitId));
        }

        if ($sort === 'unit') {
            $query->join('users', 'securities.user_id', '=', 'users.id')
                ->join('units', 'users.unit_id', '=', 'units.id')
                ->orderBy('units.unit_name', $dir)
                ->select('securities.*');
        } else {
            $query->orderBy($sortColumns[$sort], $dir);
        }

        $paginator = $query->paginate(50);

        return Inertia::render('Admin/Security/Index', [
            'securities' => $paginator->getCollection()->map(fn (Security $security) => [
                'id' => $security->id,
                'registration_number' => $security->registration_number ?? '-',
                'full_name' => $security->full_name,
                'phone_number' => $security->phone_number ?? '-',
                'kta_number' => $security->kta_number ?? '-',
                'kta_expired_date' => $security->kta_expired_date?->format('d M Y') ?? '-',
                'username' => $security->user?->username ?? '-',
                'unit_name' => $security->user?->unit?->unit_name ?? '-',
            ]),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
            'units' => $this->units($scope),
            'unit_id' => $filterUnitId ?? '',
        ]);
    }

    /**
     * Security personnel create form.
     */
    public function create(Request $request): Response
    {
        $scope = $this->unitScope($request);

        if ($scope !== null && $request->user()?->unit_id === null) {
            abort(403);
        }

        return Inertia::render('Admin/Security/Create', [
            'units' => $this->units($scope),
            'canChooseUnit' => $scope === null,
        ]);
    }

    /**
     * Store a newly created security personnel (and its user account).
     */
    public function store(Request $request): RedirectResponse
    {
        $scope = $this->unitScope($request);

        if ($scope !== null && $request->user()?->unit_id === null) {
            abort(403);
        }

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'kta_number' => ['nullable', 'string', 'max:255', Rule::unique('securities', 'kta_number')],
            'kta_issued_date' => ['required', 'date'],
            'kta_expired_date' => ['nullable', 'date'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'kta_file' => ['nullable', 'image', 'max:2048'],
            'unit_id' => $scope === null
                ? ['required', 'integer', 'exists:units,id']
                : ['nullable', 'integer'],
        ]);

        $unitId = $scope === null
            ? (int) $validated['unit_id']
            : (int) $request->user()->unit_id;

        $security = DB::transaction(function () use ($request, $validated, $unitId) {
            $user = User::create([
                'username' => $this->generateUsername($validated['full_name']),
                'name' => $validated['full_name'],
                'email' => null,
                'password' => Hash::make('satpam'),
                'role' => 'security',
                'unit_id' => $unitId,
                'is_active' => true,
            ]);

            $security = Security::create([
                'user_id' => $user->id,
                'full_name' => $validated['full_name'],
                'phone_number' => $validated['phone_number'] ?? null,
                'address' => $validated['address'] ?? null,
                'birth_place' => $validated['birth_place'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
                'kta_number' => $validated['kta_number'] ?? null,
                'kta_issued_date' => $validated['kta_issued_date'],
                'kta_expired_date' => $validated['kta_expired_date'] ?? null,
            ]);

            $files = [];

            foreach (['photo', 'kta_file'] as $field) {
                $file = $request->file($field);

                if ($file !== null) {
                    $files[$field] = $this->storeFile($file, $security, $field);
                }
            }

            if ($files !== []) {
                $security->update($files);
            }

            return $security;
        });

        return redirect()
            ->route('admin.security.index')
            ->with('success', 'Personel security berhasil ditambahkan. Username: '.$security->user?->username);
    }

    /**
     * Security personnel detail.
     */
    public function show(Request $request, int $id): Response
    {
        $security = Security::with('user.unit')->findOrFail($id);
        $this->authorizeScope($request, $security);

        return Inertia::render('Admin/Security/Show', [
            'security' => $this->transform($security),
        ]);
    }

    /**
     * Security personnel edit form.
     */
    public function edit(Request $request, int $id): Response
    {
        $security = Security::with('user.unit')->findOrFail($id);
        $this->authorizeScope($request, $security);

        $scope = $this->unitScope($request);

        return Inertia::render('Admin/Security/Edit', [
            'security' => $this->transform($security),
            'units' => $this->units($scope),
            'canChooseUnit' => $scope === null,
        ]);
    }

    /**
     * Update the specified security personnel.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $security = Security::with('user')->findOrFail($id);
        $this->authorizeScope($request, $security);

        $scope = $this->unitScope($request);

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'kta_number' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('securities', 'kta_number')->ignore($security->id),
            ],
            'kta_issued_date' => ['required', 'date'],
            'kta_expired_date' => ['nullable', 'date'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'kta_file' => ['nullable', 'image', 'max:2048'],
            'unit_id' => $scope === null
                ? ['required', 'integer', 'exists:units,id']
                : ['nullable', 'integer'],
        ]);

        $unitId = $scope === null
            ? (int) $validated['unit_id']
            : (int) $request->user()->unit_id;

        DB::transaction(function () use ($request, $validated, $unitId, $security) {
            $security->fill([
                'full_name' => $validated['full_name'],
                'phone_number' => $validated['phone_number'] ?? null,
                'address' => $validated['address'] ?? null,
                'birth_place' => $validated['birth_place'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
                'kta_number' => $validated['kta_number'] ?? null,
                'kta_issued_date' => $validated['kta_issued_date'],
                'kta_expired_date' => $validated['kta_expired_date'] ?? null,
            ]);

            foreach (['photo', 'kta_file'] as $field) {
                $file = $request->file($field);

                if ($file !== null) {
                    $this->deleteFile($security->{$field});
                    $security->{$field} = $this->storeFile($file, $security, $field);
                }
            }

            $security->save();

            if ($security->user !== null) {
                $security->user->update([
                    'name' => $validated['full_name'],
                    'unit_id' => $unitId,
                ]);
            }
        });

        return redirect()
            ->route('admin.security.show', $security->id)
            ->with('success', 'Personel security berhasil diperbarui.');
    }

    /**
     * Remove the specified security personnel.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $security = Security::with('user')->findOrFail($id);
        $this->authorizeScope($request, $security);

        try {
            DB::transaction(function () use ($security) {
                $this->deleteFile($security->photo);
                $this->deleteFile($security->kta_file);

                $user = $security->user;

                $security->delete();
                $user?->delete();
            });
        } catch (QueryException) {
            return back()->with('error', 'Tidak dapat menghapus personel security yang masih digunakan.');
        }

        return redirect()
            ->route('admin.security.index')
            ->with('success', 'Personel security berhasil dihapus.');
    }

    /**
     * Generate a unique username from the full name.
     */
    protected function generateUsername(string $fullName): string
    {
        $base = preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($fullName)));
        $username = $base;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $base.$counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Store a security personnel file on the public disk with descriptive UPPERCASE naming.
     */
    private function storeFile(UploadedFile $file, Security $security, string $field): string
    {
        $extension = \App\Support\UploadFilename::extensionFromUpload($file, 'jpg');
        $name = \App\Support\UploadFilename::short($security->full_name ?: 'PERSONEL', 12);

        if ($field === 'kta_file') {
            $directory = 'KTA';
            $filename = \App\Support\UploadFilename::dated(['KTA', $name], $extension);
        } else {
            $directory = 'security-photos';
            $filename = \App\Support\UploadFilename::dated(['PERSONEL', $name], $extension);
        }

        $stored = $file->storeAs($directory, $filename, 'public');
        if ($stored === false) {
            throw new \RuntimeException('Gagal menyimpan berkas personel.');
        }

        return $stored;
    }

    private function deleteFile(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }
        $normalized = str_replace('\\', '/', trim($path));
        if ($normalized === '' || str_contains($normalized, '../') || str_starts_with($normalized, '/')) {
            return;
        }
        if (Storage::disk('public')->exists($normalized)) {
            Storage::disk('public')->delete($normalized);
        }
    }

    /**
     * Unit scope for the current user.
     *
     * - null  => unlimited (superadmin).
     * - array => allowed unit ids (user unit + descendants), [] when none.
     */
    private function unitScope(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        if ($user->role === 'superadmin') {
            return null;
        }

        if (! in_array($user->role, ['security', 'admin'], true) || $user->unit_id === null) {
            return [];
        }

        return array_map('intval', $user->unit?->descendantIds() ?? [$user->unit_id]);
    }

    /**
     * Effective unit filter for the list (scope-safe).
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
     * Abort when the record lies outside the current unit scope.
     */
    private function authorizeScope(Request $request, Security $security): void
    {
        $scope = $this->unitScope($request);

        if ($scope === null) {
            return;
        }

        $unitId = $security->user?->unit_id;

        if ($unitId === null || ! in_array((int) $unitId, $scope, true)) {
            abort(403);
        }
    }

    private function transform(Security $security): array
    {
        return [
            'id' => $security->id,
            'registration_number' => $security->registration_number,
            'full_name' => $security->full_name,
            'phone_number' => $security->phone_number,
            'address' => $security->address,
            'birth_place' => $security->birth_place,
            'birth_date' => $security->birth_date?->format('Y-m-d'),
            'kta_number' => $security->kta_number,
            'kta_file' => $security->kta_file,
            'kta_file_url' => PublicStorageUrl::for($security->kta_file),
            'kta_issued_date' => $security->kta_issued_date?->format('Y-m-d'),
            'kta_expired_date' => $security->kta_expired_date?->format('Y-m-d'),
            'photo' => $security->photo,
            'photo_url' => PublicStorageUrl::for($security->photo),
            'username' => $security->user?->username,
            'user_id' => $security->user_id,
            'unit_id' => $security->user?->unit_id,
            'unit_name' => $security->user?->unit?->unit_name ?? '-',
            'created_at' => $security->created_at?->format('d M Y, H:i'),
            'updated_at' => $security->updated_at?->format('d M Y, H:i'),
        ];
    }

    /**
     * @param  array<int, int>|null  $scope
     * @return array<int, array{value: int, label: string}>
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
}
