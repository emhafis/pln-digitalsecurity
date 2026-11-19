<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityPost;
use App\Models\Unit;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SecurityPostController extends Controller
{
    /**
     * Security post list.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $sortColumns = [
            'code' => 'post_code',
            'name' => 'name',
            'address' => 'address',
            'dibuat' => 'created_at',
        ];

        $sort = (string) $request->query('sort', 'name');
        if (! array_key_exists($sort, $sortColumns) && $sort !== 'unit') {
            $sort = 'name';
        }
        $dir = $request->query('dir', 'asc') === 'desc' ? 'desc' : 'asc';

        $query = SecurityPost::query()
            ->with('unit')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('post_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            });

        $scope = $this->unitScope($request);
        if ($scope !== null) {
            $query->whereIn('security_posts.unit_id', $scope);
        }

        $postsNeedingCoordinates = SecurityPost::query()
            ->with('unit')
            ->when($scope !== null, fn ($query) => $query->whereIn('unit_id', $scope))
            ->where(function ($query) {
                $query->whereNull('latitude')
                    ->orWhereNull('longitude');
            })
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'unit_id'])
            ->map(fn (SecurityPost $securityPost) => [
                'id' => $securityPost->id,
                'name' => $securityPost->name,
                'unit_name' => $securityPost->unit?->unit_name ?? '-',
            ])
            ->values();

        $filterUnitId = $this->resolveFilterUnitId($request, $scope);
        if ($filterUnitId !== null) {
            $query->where('security_posts.unit_id', $filterUnitId);
        }

        if ($sort === 'unit') {
            $query->leftJoin('units', 'security_posts.unit_id', '=', 'units.id')
                ->orderBy('units.unit_name', $dir)
                ->select('security_posts.*');
        } else {
            $query->orderBy($sortColumns[$sort], $dir);
        }

        $paginator = $query->paginate(50);

        return Inertia::render('Admin/SecurityPost/Index', [
            'securityPosts' => $paginator->getCollection()->map(fn (SecurityPost $securityPost) => [
                'id' => $securityPost->id,
                'code' => $securityPost->post_code,
                'name' => $securityPost->name,
                'address' => $securityPost->address ?? '-',
                'unit_name' => $securityPost->unit?->unit_name ?? '-',
                'latitude' => $securityPost->latitude,
                'longitude' => $securityPost->longitude,
            ]),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'postsWithoutCoordinates' => $postsNeedingCoordinates->count(),
            'postsNeedingCoordinates' => $postsNeedingCoordinates,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
            'units' => $this->units($scope),
            'unit_id' => $filterUnitId ?? '',
        ]);
    }

    /**
     * Security post create form.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Admin/SecurityPost/Create', [
            'units' => $this->units($this->unitScope($request)),
        ]);
    }

    /**
     * Store a newly created security post.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'unit_id' => array_merge($this->unitIdRules($request), ['required']),
        ]);

        SecurityPost::create($validated);

        return redirect()
            ->route('admin.security-post.index')
            ->with('success', 'Post keamanan berhasil ditambahkan.');
    }

    /**
     * Security post detail.
     */
    public function show(Request $request, int $id): Response
    {
        $securityPost = SecurityPost::with('unit')->findOrFail($id);
        $this->authorizeScope($request, $securityPost);

        return Inertia::render('Admin/SecurityPost/Show', [
            'securityPost' => $this->transform($securityPost),
        ]);
    }

    /**
     * Security post edit form.
     */
    public function edit(Request $request, int $id): Response
    {
        $securityPost = SecurityPost::with('unit')->findOrFail($id);
        $this->authorizeScope($request, $securityPost);

        return Inertia::render('Admin/SecurityPost/Edit', [
            'securityPost' => $this->transform($securityPost),
            'units' => $this->units($this->unitScope($request)),
        ]);
    }

    /**
     * Update the specified security post.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $securityPost = SecurityPost::findOrFail($id);
        $this->authorizeScope($request, $securityPost);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'unit_id' => array_merge($this->unitIdRules($request), ['required']),
        ]);

        $securityPost->update($validated);

        return redirect()
            ->route('admin.security-post.show', $securityPost->id)
            ->with('success', 'Post keamanan berhasil diperbarui.');
    }

    /**
     * Remove the specified security post.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $securityPost = SecurityPost::findOrFail($id);
        $this->authorizeScope($request, $securityPost);

        try {
            DB::transaction(fn () => $securityPost->delete());
        } catch (QueryException) {
            return back()->with('error', 'Tidak dapat menghapus post keamanan yang masih digunakan.');
        }

        return redirect()
            ->route('admin.security-post.index')
            ->with('success', 'Post keamanan berhasil dihapus.');
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
     *
     * - No `unit_id` query param (first load): scoped users default to their own unit.
     * - Explicit empty param ("Semua Unit"): null => no unit filter (still scoped).
     * - Out-of-scope or invalid value: ignored (null => all within scope).
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
     * Validation rules for unit_id based on the current unit scope.
     */
    private function unitIdRules(Request $request): array
    {
        $scope = $this->unitScope($request);

        return $scope === null
            ? ['integer', 'exists:units,id']
            : ['integer', Rule::in($scope)];
    }

    /**
     * Abort when the record lies outside the current unit scope.
     */
    private function authorizeScope(Request $request, SecurityPost $securityPost): void
    {
        $scope = $this->unitScope($request);

        if ($scope !== null && ! in_array((int) $securityPost->unit_id, $scope, true)) {
            abort(403);
        }
    }

    private function transform(SecurityPost $securityPost): array
    {
        return [
            'id' => $securityPost->id,
            'post_code' => $securityPost->post_code,
            'name' => $securityPost->name,
            'address' => $securityPost->address,
            'latitude' => $securityPost->latitude,
            'longitude' => $securityPost->longitude,
            'unit_id' => $securityPost->unit_id,
            'unit_name' => $securityPost->unit?->unit_name ?? '-',
            'created_at' => $securityPost->created_at?->format('d M Y, H:i'),
            'updated_at' => $securityPost->updated_at?->format('d M Y, H:i'),
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
