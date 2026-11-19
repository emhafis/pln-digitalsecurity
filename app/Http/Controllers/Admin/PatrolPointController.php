<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PatrolPoint;
use App\Models\SecurityPost;
use App\Models\Unit;
use App\Services\PatrolPointQrCodeService;
use Dompdf\Dompdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PatrolPointController extends Controller
{
    public function __construct(
        private readonly PatrolPointQrCodeService $qrCodeService
    ) {}

    /**
     * Security post list with patrol point counts.
     */
    public function index(Request $request): InertiaResponse
    {
        $search = trim((string) $request->query('search', ''));

        $sortColumns = [
            'name' => 'security_posts.name',
            'code' => 'security_posts.post_code',
            'dibuat' => 'security_posts.created_at',
        ];

        $sort = (string) $request->query('sort', 'name');
        if (! array_key_exists($sort, $sortColumns) && ! in_array($sort, ['unit', 'points'], true)) {
            $sort = 'name';
        }
        $dir = $request->query('dir', 'asc') === 'desc' ? 'desc' : 'asc';

        $query = SecurityPost::query()
            ->with('unit')
            ->withCount('patrolPoints')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('security_posts.name', 'like', "%{$search}%")
                        ->orWhere('security_posts.post_code', 'like', "%{$search}%")
                        ->orWhereHas('unit', function ($query) use ($search) {
                            $query->where('unit_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('patrolPoints', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%");
                        });
                });
            });

        $scope = $this->unitScope($request);
        if ($scope !== null) {
            $query->whereIn('security_posts.unit_id', $scope);
        }

        $filterUnitId = $this->resolveFilterUnitId($request, $scope);
        if ($filterUnitId !== null) {
            $query->where('security_posts.unit_id', $filterUnitId);
        }

        if ($sort === 'unit') {
            $query->leftJoin('units', 'security_posts.unit_id', '=', 'units.id')
                ->orderBy('units.unit_name', $dir);
        } elseif ($sort === 'points') {
            $query->orderBy('patrol_points_count', $dir);
        } else {
            $query->orderBy($sortColumns[$sort], $dir);
        }

        $query->orderBy('security_posts.id');

        $paginator = $query->paginate(50);

        return Inertia::render('Admin/PatrolPoint/Index', [
            'securityPosts' => $paginator->getCollection()->map(fn (SecurityPost $securityPost) => [
                'id' => $securityPost->id,
                'code' => $securityPost->post_code,
                'name' => $securityPost->name,
                'unit_id' => $securityPost->unit_id,
                'unit_name' => $securityPost->unit?->unit_name ?? '-',
                'patrol_points_count' => $securityPost->patrol_points_count,
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
     * Patrol point create form.
     */
    public function create(Request $request): InertiaResponse
    {
        $scope = $this->unitScope($request);
        $selectedPostId = $request->integer('security_post_id');

        if ($selectedPostId <= 0 || ! SecurityPost::query()
            ->whereKey($selectedPostId)
            ->when($scope !== null, fn ($query) => $query->whereIn('unit_id', $scope))
            ->exists()) {
            $selectedPostId = null;
        }

        return Inertia::render('Admin/PatrolPoint/Create', [
            'securityPosts' => $this->securityPosts($scope),
            'securityPostId' => $selectedPostId,
        ]);
    }

    /**
     * Store a newly created patrol point.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'security_post_id' => array_merge($this->securityPostIdRules($request), ['required']),
        ]);

        $validated['qr_code'] = (string) Str::uuid();

        $patrolPoint = PatrolPoint::create($validated);

        return redirect()
            ->route('admin.patrol-point.post.show', $patrolPoint->security_post_id)
            ->with('success', 'Titik patroli berhasil ditambahkan.');
    }

    /**
     * Patrol point detail.
     */
    public function show(Request $request, int $id): InertiaResponse
    {
        $patrolPoint = PatrolPoint::with('securityPost.unit')->findOrFail($id);
        $this->authorizeScope($request, $patrolPoint);

        return Inertia::render('Admin/PatrolPoint/Show', [
            'patrolPoint' => $this->transform($patrolPoint),
        ]);
    }

    /**
     * Security post detail with its patrol points.
     */
    public function showPost(Request $request, int $id): InertiaResponse
    {
        $securityPost = SecurityPost::query()
            ->with('unit')
            ->withCount('patrolPoints')
            ->findOrFail($id);

        $this->authorizePostScope($request, $securityPost);

        $patrolPoints = $securityPost->patrolPoints()
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name']);

        return Inertia::render('Admin/PatrolPoint/ShowPost', [
            'securityPost' => [
                'id' => $securityPost->id,
                'code' => $securityPost->post_code,
                'name' => $securityPost->name,
                'unit_name' => $securityPost->unit?->unit_name ?? '-',
                'patrol_points_count' => $securityPost->patrol_points_count,
            ],
            'patrolPoints' => $patrolPoints->map(fn (PatrolPoint $patrolPoint) => [
                'id' => $patrolPoint->id,
                'name' => $patrolPoint->name,
            ])->values(),
        ]);
    }

    /**
     * Download all patrol point QR codes for a security post as PDF.
     */
    public function qrPdf(Request $request, int $id): HttpResponse|RedirectResponse
    {
        $securityPost = SecurityPost::query()->with('unit')->findOrFail($id);
        $this->authorizePostScope($request, $securityPost);

        $patrolPoints = $securityPost->patrolPoints()
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'qr_code']);

        if ($patrolPoints->isEmpty()) {
            return redirect()
                ->route('admin.patrol-point.post.show', $securityPost->id)
                ->with('warning', 'Pos jaga belum memiliki titik patroli. QR Code tidak dapat diunduh.');
        }

        $points = $patrolPoints->map(fn (PatrolPoint $patrolPoint) => [
            'name' => $patrolPoint->name,
            'data_uri' => $this->qrCodeService->dataUri((string) $patrolPoint->qr_code),
        ]);

        $printedAt = now()->timezone('Asia/Jakarta')->format('d-m-Y H:i').' WIB';

        $pdf = new Dompdf([
            'defaultFont' => 'DejaVu Sans',
            'isRemoteEnabled' => false,
        ]);
        $pdf->loadHtml(view('admin.patrol-point.qr-pdf', [
            'securityPost' => $securityPost,
            'points' => $points,
            'printedAt' => $printedAt,
        ])->render(), 'UTF-8');
        $pdf->setPaper('a4', 'portrait');
        $pdf->render();

        $filename = 'qr-titik-patroli-'.(Str::slug($securityPost->name) ?: 'pos-jaga').'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    /**
     * Patrol point edit form.
     */
    public function edit(Request $request, int $id): InertiaResponse
    {
        $patrolPoint = PatrolPoint::with('securityPost.unit')->findOrFail($id);
        $this->authorizeScope($request, $patrolPoint);

        return Inertia::render('Admin/PatrolPoint/Edit', [
            'patrolPoint' => $this->transform($patrolPoint),
            'securityPosts' => $this->securityPosts($this->unitScope($request)),
        ]);
    }

    /**
     * Update the specified patrol point.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $patrolPoint = PatrolPoint::findOrFail($id);
        $this->authorizeScope($request, $patrolPoint);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'security_post_id' => array_merge($this->securityPostIdRules($request), ['required']),
        ]);

        $patrolPoint->update($validated);

        return redirect()
            ->route('admin.patrol-point.post.show', $validated['security_post_id'])
            ->with('success', 'Titik patroli berhasil diperbarui.');
    }

    /**
     * Remove the specified patrol point.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $patrolPoint = PatrolPoint::findOrFail($id);
        $this->authorizeScope($request, $patrolPoint);

        $securityPostId = $patrolPoint->security_post_id;

        try {
            DB::transaction(fn () => $patrolPoint->delete());
        } catch (QueryException) {
            return back()->with('error', 'Tidak dapat menghapus titik patroli yang masih digunakan.');
        }

        return redirect()
            ->route('admin.patrol-point.post.show', $securityPostId)
            ->with('success', 'Titik patroli berhasil dihapus.');
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
     * Validation rules for security_post_id based on the current unit scope.
     */
    private function securityPostIdRules(Request $request): array
    {
        $scope = $this->unitScope($request);

        if ($scope === null) {
            return ['integer', 'exists:security_posts,id'];
        }

        $ids = SecurityPost::query()
            ->whereIn('unit_id', $scope)
            ->pluck('id')
            ->all();

        return ['integer', Rule::in($ids)];
    }

    /**
     * Abort when the record lies outside the current unit scope.
     */
    private function authorizeScope(Request $request, PatrolPoint $patrolPoint): void
    {
        $scope = $this->unitScope($request);

        if ($scope === null) {
            return;
        }

        $unitId = $patrolPoint->securityPost?->unit_id;

        if ($unitId === null || ! in_array((int) $unitId, $scope, true)) {
            abort(403);
        }
    }

    /**
     * Abort when the security post lies outside the current unit scope.
     */
    private function authorizePostScope(Request $request, SecurityPost $securityPost): void
    {
        $scope = $this->unitScope($request);

        if ($scope === null) {
            return;
        }

        if ($securityPost->unit_id === null || ! in_array((int) $securityPost->unit_id, $scope, true)) {
            abort(403);
        }
    }

    private function transform(PatrolPoint $patrolPoint): array
    {
        return [
            'id' => $patrolPoint->id,
            'qr_code' => $patrolPoint->qr_code,
            'name' => $patrolPoint->name,
            'security_post_id' => $patrolPoint->security_post_id,
            'security_post_name' => $patrolPoint->securityPost?->name ?? '-',
            'unit_name' => $patrolPoint->securityPost?->unit?->unit_name ?? '-',
            'created_at' => $patrolPoint->created_at?->format('d M Y, H:i'),
            'updated_at' => $patrolPoint->updated_at?->format('d M Y, H:i'),
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

    /**
     * @param  array<int, int>|null  $scope
     * @return array<int, array{value: int, label: string}>
     */
    private function securityPosts(?array $scope): array
    {
        return SecurityPost::query()
            ->with('unit')
            ->when($scope !== null, fn ($query) => $query->whereIn('unit_id', $scope))
            ->orderBy('name')
            ->get()
            ->map(fn (SecurityPost $securityPost) => [
                'value' => $securityPost->id,
                'label' => $securityPost->unit
                    ? $securityPost->name.' - '.$securityPost->unit->unit_name
                    : $securityPost->name,
            ])
            ->toArray();
    }
}
