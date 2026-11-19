<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    /**
     * Unit list.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $sortColumns = [
            'code' => 'unit_code',
            'name' => 'unit_name',
            'level' => 'unit_level',
            'status' => 'is_active',
            'dibuat' => 'created_at',
        ];

        $sort = (string) $request->query('sort', 'name');
        if (! array_key_exists($sort, $sortColumns)) {
            $sort = 'name';
        }
        $dir = $request->query('dir', 'asc') === 'desc' ? 'desc' : 'asc';

        $scope = $this->unitScope($request);

        $query = Unit::query()
            ->when($scope !== null, fn ($query) => $query->whereIn('id', $scope))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('unit_code', 'like', "%{$search}%")
                        ->orWhere('unit_name', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortColumns[$sort], $dir);

        $paginator = $query->paginate(50);

        return Inertia::render('Admin/Unit/Index', [
            'units' => $paginator->getCollection()->map(fn (Unit $unit) => [
                'id' => $unit->id,
                'code' => $unit->unit_code,
                'name' => $unit->unit_name,
                'level' => strtoupper($unit->unit_level),
                'status' => $unit->is_active ? 'Aktif' : 'Nonaktif',
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
        ]);
    }

    /**
     * Unit create form.
     */
    public function create(Request $request): Response
    {
        abort_unless($this->isSuperadmin($request), 403);

        return Inertia::render('Admin/Unit/Create');
    }

    /**
     * Store a newly created unit.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->isSuperadmin($request), 403);

        $request->merge(['unit_name' => Str::upper((string) $request->input('unit_name'))]);

        $validated = $request->validate([
            'unit_name' => ['required', 'string', 'max:255', 'unique:units,unit_name'],
            'unit_level' => ['required', 'string', 'in:uid,up,ulp'],
        ]);

        Unit::create($validated);

        return redirect()
            ->route('admin.unit.index')
            ->with('success', 'Unit berhasil ditambahkan.');
    }

    /**
     * Unit edit form.
     */
    public function edit(Request $request, int $id): Response
    {
        $unit = $this->scopedUnit($request, $id);

        return Inertia::render('Admin/Unit/Edit', [
            'unit' => $this->transform($unit),
        ]);
    }

    /**
     * Unit detail.
     */
    public function show(Request $request, int $id): Response
    {
        $unit = $this->scopedUnit($request, $id)->load('parent');

        return Inertia::render('Admin/Unit/Show', [
            'unit' => [
                'id' => $unit->id,
                'code' => $unit->unit_code,
                'name' => $unit->unit_name,
                'level' => strtoupper($unit->unit_level),
                'parent' => $unit->parent?->unit_name ?? '-',
                'status' => $unit->is_active ? 'Aktif' : 'Nonaktif',
                'created_at' => $unit->created_at?->format('d M Y, H:i'),
                'updated_at' => $unit->updated_at?->format('d M Y, H:i'),
            ],
        ]);
    }

    /**
     * Update the specified unit.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $unit = $this->scopedUnit($request, $id);

        $request->merge(['unit_name' => Str::upper((string) $request->input('unit_name'))]);

        $validated = $request->validate([
            'unit_name' => ['required', 'string', 'max:255', 'unique:units,unit_name,'.$unit->id],
            'unit_level' => ['required', 'string', 'in:uid,up,ulp'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $unit->update($validated);

        return redirect()
            ->route('admin.unit.show', $unit->id)
            ->with('success', 'Unit berhasil diperbarui.');
    }

    /**
     * Remove the specified unit.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $unit = $this->scopedUnit($request, $id);

        try {
            DB::transaction(fn () => $unit->delete());
        } catch (QueryException) {
            return back()->with('error', 'Tidak dapat menghapus unit yang memiliki sub-unit.');
        }

        return redirect()
            ->route('admin.unit.index')
            ->with('success', 'Unit berhasil dihapus.');
    }

    /**
     * Unit scope for the current user.
     *
     * - null  => unlimited (superadmin).
     * - array => allowed unit ids (user unit + descendants), [] when none.
     *
     * @return array<int, int>|null
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

        if ($user->role !== 'admin' || $user->unit_id === null) {
            return [];
        }

        return array_map('intval', $user->unit?->descendantIds() ?? [(int) $user->unit_id]);
    }

    private function scopedUnit(Request $request, int $id): Unit
    {
        $unit = Unit::findOrFail($id);
        $scope = $this->unitScope($request);

        if ($scope !== null && ! in_array((int) $unit->id, $scope, true)) {
            abort(403);
        }

        return $unit;
    }

    private function isSuperadmin(Request $request): bool
    {
        return $request->user()?->role === 'superadmin';
    }

    private function transform(Unit $unit): array
    {
        return [
            'id' => $unit->id,
            'unit_code' => $unit->unit_code,
            'unit_name' => $unit->unit_name,
            'unit_level' => $unit->unit_level,
            'is_active' => $unit->is_active,
        ];
    }
}
