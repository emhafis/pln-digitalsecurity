<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\SchedulePeriod;
use App\Models\Security;
use App\Models\SecurityPost;
use App\Models\SecurityTeam;
use App\Models\SecurityTeamMember;
use App\Models\Unit;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SecurityTeamController extends Controller
{
    /**
     * Security team list.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $sortColumns = [
            'code' => 'security_teams.code',
            'name' => 'security_teams.name',
            'status' => 'security_teams.is_active',
            'members' => 'active_members_count',
            'dibuat' => 'security_teams.created_at',
        ];

        $sort = (string) $request->query('sort', 'name');
        if (! array_key_exists($sort, $sortColumns) && ! in_array($sort, ['unit', 'post'], true)) {
            $sort = 'name';
        }
        $dir = $request->query('dir', 'asc') === 'desc' ? 'desc' : 'asc';

        $activeFilter = (string) $request->query('is_active', '');
        if (! in_array($activeFilter, ['0', '1'], true)) {
            $activeFilter = '';
        }

        $query = SecurityTeam::query()
            ->with(['securityPost.unit'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('security_teams.code', 'like', "%{$search}%")
                        ->orWhere('security_teams.name', 'like', "%{$search}%")
                        ->orWhere('security_teams.description', 'like', "%{$search}%");
                });
            })
            ->when($activeFilter !== '', function ($query) use ($activeFilter) {
                $query->where('security_teams.is_active', $activeFilter === '1');
            });

        $scope = $this->unitScope($request);
        if ($scope !== null) {
            $query->whereHas('securityPost', fn ($postQuery) => $postQuery->whereIn('unit_id', $scope));
        }

        $filterUnitId = $this->resolveFilterUnitId($request, $scope);
        if ($filterUnitId !== null) {
            $query->whereHas('securityPost', fn ($postQuery) => $postQuery->where('unit_id', $filterUnitId));
        }

        $filterPostId = $this->resolveFilterPostId($request, $scope, $filterUnitId);
        if ($filterPostId !== null) {
            $query->where('security_teams.security_post_id', $filterPostId);
        }

        if ($sort === 'unit') {
            $query->leftJoin('security_posts', 'security_teams.security_post_id', '=', 'security_posts.id')
                ->leftJoin('units', 'security_posts.unit_id', '=', 'units.id')
                ->orderBy('units.unit_name', $dir)
                ->select('security_teams.*');
        } elseif ($sort === 'post') {
            $query->leftJoin('security_posts', 'security_teams.security_post_id', '=', 'security_posts.id')
                ->orderBy('security_posts.name', $dir)
                ->select('security_teams.*');
        } else {
            $query->orderBy($sortColumns[$sort], $dir);
        }

        $query->withCount(['activeMembers', 'members', 'schedulePeriods']);

        $paginator = $query->paginate(50);

        return Inertia::render('Admin/SecurityTeam/Index', [
            'teams' => $paginator->getCollection()->map(fn (SecurityTeam $team) => $this->listItem($team)),
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
            'is_active' => $activeFilter,
            'units' => $this->units($scope),
            'posts' => $this->posts($scope),
            'unit_id' => $filterUnitId ?? '',
            'security_post_id' => $filterPostId ?? '',
        ]);
    }

    /**
     * Security team create form.
     */
    public function create(Request $request): Response
    {
        $scope = $this->unitScope($request);

        if ($scope !== null && $request->user()?->unit_id === null) {
            abort(403);
        }

        return Inertia::render('Admin/SecurityTeam/Create', [
            'units' => $this->units($scope),
            'posts' => $this->posts($scope),
        ]);
    }

    /**
     * Store a newly created security team.
     */
    public function store(Request $request): RedirectResponse
    {
        $scope = $this->unitScope($request);

        if ($scope !== null && $request->user()?->unit_id === null) {
            abort(403);
        }

        $this->normalizeInput($request);

        [$validated] = $this->validateTeam($request, null, $scope);

        $team = SecurityTeam::create($validated);

        return redirect()
            ->route('admin.security-team.show', $team->id)
            ->with('success', 'Regu berhasil ditambahkan.');
    }

    /**
     * Security team detail with active members and membership history.
     */
    public function show(Request $request, int $id): Response
    {
        $team = SecurityTeam::query()
            ->with(['securityPost.unit'])
            ->withCount(['activeMembers', 'members', 'schedulePeriods'])
            ->findOrFail($id);

        $this->authorizeScope($request, $team);

        $members = $team->members()
            ->with(['security.user.unit'])
            ->orderByDesc('joined_at')
            ->orderByDesc('id')
            ->get();

        $runningIds = $this->runningScheduleSecurityIds(
            $members->pluck('security_id')->unique()->values()->all()
        );

        $referenced = $this->isReferenced($team);

        return Inertia::render('Admin/SecurityTeam/Show', [
            'team' => $this->transform($team),
            'activeMembers' => $members->whereNull('left_at')->values()
                ->map(fn (SecurityTeamMember $member) => $this->memberItem($member, $runningIds))
                ->all(),
            'history' => $members
                ->map(fn (SecurityTeamMember $member) => $this->memberItem($member, $runningIds))
                ->all(),
            'securityOptions' => $this->securityOptions($team, $this->unitScope($request), $request->user()?->unit_id),
            'teamOptions' => $this->teamOptions($this->unitScope($request), (int) $team->id),
            'canAddMember' => $team->is_active,
            'canDelete' => ! $referenced,
            'postLocked' => $referenced,
        ]);
    }

    /**
     * Security team edit form.
     */
    public function edit(Request $request, int $id): Response
    {
        $team = SecurityTeam::query()
            ->with(['securityPost.unit'])
            ->withCount(['activeMembers', 'members', 'schedulePeriods'])
            ->findOrFail($id);

        $this->authorizeScope($request, $team);

        $scope = $this->unitScope($request);

        return Inertia::render('Admin/SecurityTeam/Edit', [
            'team' => $this->transform($team),
            'units' => $this->units($scope),
            'posts' => $this->posts($scope),
            'postLocked' => $this->isReferenced($team),
        ]);
    }

    /**
     * Update the specified security team.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $team = SecurityTeam::findOrFail($id);
        $this->authorizeScope($request, $team);

        $scope = $this->unitScope($request);

        $this->normalizeInput($request);

        [$validated] = $this->validateTeam($request, $team, $scope);

        if (
            $this->isReferenced($team)
            && (int) $validated['security_post_id'] !== (int) $team->security_post_id
        ) {
            throw ValidationException::withMessages([
                'security_post_id' => 'Pos jaga regu tidak dapat diubah karena regu sudah memiliki riwayat anggota atau periode jadwal.',
            ]);
        }

        $team->update($validated);

        return redirect()
            ->route('admin.security-team.show', $team->id)
            ->with('success', 'Regu berhasil diperbarui.');
    }

    /**
     * Toggle the security team active status.
     */
    public function toggle(Request $request, int $id): RedirectResponse
    {
        $team = SecurityTeam::findOrFail($id);
        $this->authorizeScope($request, $team);

        $team->update(['is_active' => ! $team->is_active]);

        $message = $team->is_active
            ? 'Regu berhasil diaktifkan.'
            : 'Regu berhasil dinonaktifkan. Regu nonaktif tidak dapat menerima anggota baru atau digunakan untuk periode jadwal baru.';

        return back()->with('success', $message);
    }

    /**
     * Remove the specified security team when it has no history or periods.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $team = SecurityTeam::findOrFail($id);
        $this->authorizeScope($request, $team);

        if ($this->isReferenced($team)) {
            return back()->with(
                'error',
                'Regu tidak dapat dihapus karena sudah memiliki riwayat anggota atau periode jadwal. Nonaktifkan regu jika tidak ingin dipakai lagi.'
            );
        }

        try {
            DB::transaction(fn () => $team->delete());
        } catch (QueryException) {
            return back()->with('error', 'Tidak dapat menghapus regu yang masih digunakan.');
        }

        return redirect()
            ->route('admin.security-team.index')
            ->with('success', 'Regu berhasil dihapus.');
    }

    /**
     * Add a member to a team or move an active member to another team.
     */
    public function storeMember(Request $request, int $id): RedirectResponse
    {
        $team = SecurityTeam::query()->with(['securityPost.unit'])->findOrFail($id);
        $this->authorizeScope($request, $team);

        $validated = $request->validate([
            'security_id' => ['nullable', 'integer', 'exists:securities,id'],
            'security_ids' => ['nullable', 'array'],
            'security_ids.*' => ['integer', 'exists:securities,id'],
            'joined_at' => ['required', 'date_format:Y-m-d'],
            'target_security_team_id' => ['nullable', 'integer', 'exists:security_teams,id'],
        ]);

        $securityIds = collect($validated['security_ids'] ?? [])
            ->merge(isset($validated['security_id']) ? [$validated['security_id']] : [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($securityIds->isEmpty()) {
            throw ValidationException::withMessages([
                'security_id' => 'Satpam wajib dipilih.',
            ]);
        }

        $target = $team;

        if (
            ! empty($validated['target_security_team_id'])
            && (int) $validated['target_security_team_id'] !== (int) $team->id
        ) {
            $target = SecurityTeam::query()
                ->with(['securityPost.unit'])
                ->findOrFail((int) $validated['target_security_team_id']);

            $this->authorizeScope($request, $target);
        }

        if (! $target->is_active) {
            $errorKey = (int) $target->id === (int) $team->id
                ? 'security_id'
                : 'target_security_team_id';

            throw ValidationException::withMessages([
                $errorKey => 'Regu nonaktif sehingga tidak dapat menerima anggota baru.',
            ]);
        }

        $postUnit = $team->securityPost?->unit;
        $allowedUnitIds = $postUnit !== null
            ? $postUnit->descendantIds()
            : [];

        $scope = $this->unitScope($request);

        $exactUnitId = $request->user()?->unit_id;

        DB::transaction(function () use ($target, $securityIds, $validated, $allowedUnitIds, $scope, $exactUnitId) {
            foreach ($securityIds as $securityId) {
                $security = Security::query()->with(['user.unit'])->findOrFail((int) $securityId);

                if ($security->user === null || $security->user->unit_id === null) {
                    throw ValidationException::withMessages([
                        'security_id' => 'Satpam belum memiliki user atau unit yang valid.',
                    ]);
                }

                if ($scope !== null && ! in_array((int) $security->user->unit_id, $scope, true)) {
                    throw ValidationException::withMessages([
                        'security_id' => 'Satpam berada di luar unit yang dapat diakses.',
                    ]);
                }

                if ($exactUnitId !== null && (int) $security->user->unit_id !== (int) $exactUnitId) {
                    throw ValidationException::withMessages([
                        'security_id' => 'Satpam berada di luar unit yang dapat diakses.',
                    ]);
                }

                if ($allowedUnitIds !== [] && ! in_array((int) $security->user->unit_id, $allowedUnitIds, true)) {
                    throw ValidationException::withMessages([
                        'security_id' => 'Unit satpam tidak sesuai dengan unit pos jaga regu.',
                    ]);
                }

                $target->assignMember($security, $validated['joined_at']);
            }
        });

        $response = redirect()
            ->route('admin.security-team.show', $team->id)
            ->with('success', 'Anggota regu berhasil ditambahkan.');

        foreach ($securityIds as $securityId) {
            if ($this->hasRunningSchedule((int) $securityId)) {
                $response->with(
                    'warning',
                    'Satpam ini masih memiliki jadwal pada periode draft/aktif. Jadwal dan snapshot periode tidak diubah oleh perubahan keanggotaan.'
                );
                break;
            }
        }

        return $response;
    }

    /**
     * End an active team membership by setting left_at.
     */
    public function endMember(Request $request, int $id, int $memberId): RedirectResponse
    {
        $team = SecurityTeam::query()->with(['securityPost.unit'])->findOrFail($id);
        $this->authorizeScope($request, $team);

        $validated = $request->validate([
            'left_at' => ['required', 'date_format:Y-m-d'],
        ]);

        $member = DB::transaction(function () use ($team, $memberId, $validated) {
            $member = SecurityTeamMember::query()
                ->whereKey($memberId)
                ->where('security_team_id', $team->id)
                ->firstOrFail();

            // Keep the lock order consistent with assignment: parent security
            // row first, then the membership row.
            Security::query()
                ->whereKey($member->security_id)
                ->lockForUpdate()
                ->firstOrFail();

            $member = SecurityTeamMember::query()
                ->whereKey($memberId)
                ->where('security_team_id', $team->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($member->left_at !== null) {
                throw ValidationException::withMessages([
                    'left_at' => 'Keanggotaan ini sudah berakhir.',
                ]);
            }

            $leftAt = Carbon::parse($validated['left_at'])->startOfDay();

            if ($leftAt->lt($member->joined_at)) {
                throw ValidationException::withMessages([
                    'left_at' => 'Tanggal berakhir tidak boleh lebih awal dari tanggal bergabung.',
                ]);
            }

            $member->update(['left_at' => $leftAt->toDateString()]);

            return $member;
        });

        $response = redirect()
            ->route('admin.security-team.show', $team->id)
            ->with('success', 'Keanggotaan regu berhasil diakhiri.');

        if ($this->hasRunningSchedule((int) $member->security_id)) {
            $response->with(
                'warning',
                'Satpam ini masih memiliki jadwal pada periode draft/aktif. Jadwal dan snapshot periode tidak diubah oleh perubahan keanggotaan.'
            );
        }

        return $response;
    }

    /**
     * @param  array<int, int>|null  $scope
     * @return array{0: array<string, mixed>, 1: SecurityPost}
     */
    private function validateTeam(Request $request, ?SecurityTeam $team, ?array $scope): array
    {
        $postId = (int) $request->input('security_post_id');
        $name = trim((string) $request->input('name'));

        if ($team !== null && $this->isReferenced($team) && $name !== $team->name) {
            throw ValidationException::withMessages([
                'name' => 'Nama dan kode regu tidak dapat diubah karena regu sudah memiliki riwayat anggota atau periode jadwal.',
            ]);
        }

        $request->merge([
            'code' => SecurityTeam::codeForName($name),
        ]);

        $validated = $request->validate([
            'security_post_id' => ['required', 'integer', 'exists:security_posts,id'],
            'name' => [
                'required',
                'string',
                Rule::in(array_values(SecurityTeam::NAMES)),
                Rule::unique('security_teams', 'name')
                    ->where(fn ($query) => $query->where('security_post_id', $postId))
                    ->ignore($team?->id),
            ],
            'code' => [
                'required',
                'string',
                Rule::in(array_keys(SecurityTeam::NAMES)),
                Rule::unique('security_teams', 'code')
                    ->where(fn ($query) => $query->where('security_post_id', $postId))
                    ->ignore($team?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $post = SecurityPost::query()->with('unit')->findOrFail($postId);

        if ($scope !== null && ! in_array((int) $post->unit_id, $scope, true)) {
            throw ValidationException::withMessages([
                'security_post_id' => 'Pos jaga berada di luar unit yang dapat diakses.',
            ]);
        }

        $validated['is_active'] = $request->has('is_active')
            ? $request->boolean('is_active')
            : ($team?->is_active ?? true);

        return [$validated, $post];
    }

    private function normalizeInput(Request $request): void
    {
        $description = trim((string) $request->input('description', ''));

        $request->merge([
            'name' => trim((string) $request->input('name')),
            'description' => $description === '' ? null : $description,
        ]);
    }

    private function isReferenced(SecurityTeam $team): bool
    {
        return $team->members()->exists() || $team->schedulePeriods()->exists();
    }

    private function hasRunningSchedule(int $securityId): bool
    {
        return Schedule::query()
            ->where('security_id', $securityId)
            ->whereHas('schedulePeriod', fn ($query) => $query->whereIn('status', [
                SchedulePeriod::STATUS_DRAFT,
                SchedulePeriod::STATUS_ACTIVE,
            ]))
            ->exists();
    }

    /**
     * @param  array<int, int>  $securityIds
     * @return array<int, int>
     */
    private function runningScheduleSecurityIds(array $securityIds): array
    {
        if ($securityIds === []) {
            return [];
        }

        return Schedule::query()
            ->whereIn('security_id', $securityIds)
            ->whereHas('schedulePeriod', fn ($query) => $query->whereIn('status', [
                SchedulePeriod::STATUS_DRAFT,
                SchedulePeriod::STATUS_ACTIVE,
            ]))
            ->distinct()
            ->pluck('security_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function listItem(SecurityTeam $team): array
    {
        return [
            'id' => $team->id,
            'code' => $team->code,
            'name' => $team->name,
            'description' => $team->description,
            'is_active' => $team->is_active,
            'status' => $team->is_active ? 'Aktif' : 'Nonaktif',
            'security_post_id' => $team->security_post_id,
            'post_code' => $team->securityPost?->post_code,
            'post_name' => $team->securityPost?->name ?? '-',
            'unit_id' => $team->securityPost?->unit_id,
            'unit_name' => $team->securityPost?->unit?->unit_name ?? '-',
            'active_members_count' => (int) ($team->active_members_count ?? 0),
            'members_count' => (int) ($team->members_count ?? 0),
            'schedule_periods_count' => (int) ($team->schedule_periods_count ?? 0),
            'is_referenced' => ((int) ($team->members_count ?? 0) + (int) ($team->schedule_periods_count ?? 0)) > 0,
            'created_at' => $team->created_at?->format('d M Y, H:i'),
        ];
    }

    private function transform(SecurityTeam $team): array
    {
        return [
            'id' => $team->id,
            'code' => $team->code,
            'name' => $team->name,
            'description' => $team->description,
            'is_active' => $team->is_active,
            'status' => $team->is_active ? 'Aktif' : 'Nonaktif',
            'security_post_id' => $team->security_post_id,
            'post_code' => $team->securityPost?->post_code,
            'post_name' => $team->securityPost?->name ?? '-',
            'unit_id' => $team->securityPost?->unit_id,
            'unit_name' => $team->securityPost?->unit?->unit_name ?? '-',
            'active_members_count' => (int) ($team->active_members_count ?? 0),
            'members_count' => (int) ($team->members_count ?? 0),
            'schedule_periods_count' => (int) ($team->schedule_periods_count ?? 0),
            'is_referenced' => ((int) ($team->members_count ?? 0) + (int) ($team->schedule_periods_count ?? 0)) > 0,
            'created_at' => $team->created_at?->format('d M Y, H:i'),
            'updated_at' => $team->updated_at?->format('d M Y, H:i'),
        ];
    }

    /**
     * @param  array<int, int>  $runningIds
     */
    private function memberItem(SecurityTeamMember $member, array $runningIds): array
    {
        return [
            'id' => $member->id,
            'security_id' => $member->security_id,
            'full_name' => $member->security?->full_name ?? '-',
            'registration_number' => $member->security?->registration_number,
            'unit_id' => $member->security?->user?->unit_id,
            'unit_name' => $member->security?->user?->unit?->unit_name ?? '-',
            'joined_at' => $member->joined_at?->toDateString(),
            'left_at' => $member->left_at?->toDateString(),
            'is_active' => $member->left_at === null,
            'has_running_schedule' => in_array((int) $member->security_id, $runningIds, true),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function securityOptions(SecurityTeam $team, ?array $scope = null, ?int $exactUnitId = null): array
    {
        $team->loadMissing('securityPost.unit');

        $postUnitId = $team->securityPost?->unit_id;
        $unitIds = $postUnitId !== null
            ? ($team->securityPost?->unit?->descendantIds() ?? [$postUnitId])
            : [];

        if ($unitIds === []) {
            return [];
        }

        if ($scope !== null) {
            $unitIds = array_values(array_intersect($unitIds, $scope));
        }

        if ($exactUnitId !== null) {
            $unitIds = array_values(array_intersect($unitIds, [$exactUnitId]));
        }

        if ($unitIds === []) {
            return [];
        }

        return Security::query()
            ->with([
                'user:id,unit_id',
                'user.unit:id,unit_name',
                'activeSecurityTeamMembership.securityTeam:id,name,code',
            ])
            ->whereHas('user', fn ($query) => $query->whereIn('unit_id', $unitIds))
            ->orderBy('full_name')
            ->get(['id', 'user_id', 'full_name', 'registration_number'])
            ->map(fn (Security $security) => [
                'value' => $security->id,
                'label' => $security->full_name.($security->registration_number ? ' ('.$security->registration_number.')' : ''),
                'unit_id' => $security->user?->unit_id,
                'unit_name' => $security->user?->unit?->unit_name ?? '-',
                'active_team_id' => $security->activeSecurityTeamMembership?->security_team_id,
                'active_team_name' => $security->activeSecurityTeamMembership?->securityTeam?->name,
                'active_team_code' => $security->activeSecurityTeamMembership?->securityTeam?->code,
                'eligible' => true,
                'disabled_reason' => null,
            ])
            ->toArray();
    }

    /**
     * @param  array<int, int>|null  $scope
     * @return array<int, array<string, mixed>>
     */
    private function teamOptions(?array $scope, ?int $excludeId = null): array
    {
        return SecurityTeam::query()
            ->where('is_active', true)
            ->when($excludeId !== null, fn ($query) => $query->whereKeyNot($excludeId))
            ->when($scope !== null, function ($query) use ($scope) {
                $query->whereHas('securityPost', fn ($postQuery) => $postQuery->whereIn('unit_id', $scope));
            })
            ->with(['securityPost.unit'])
            ->orderBy('name')
            ->get(['id', 'security_post_id', 'name', 'code'])
            ->map(fn (SecurityTeam $team) => [
                'value' => $team->id,
                'label' => $team->code.' — '.$team->name,
                'security_post_id' => $team->security_post_id,
                'post_name' => $team->securityPost?->name,
                'unit_id' => $team->securityPost?->unit_id,
                'unit_name' => $team->securityPost?->unit?->unit_name,
            ])
            ->toArray();
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
     * @return array<int, array{value: int, label: string, unit_id: int}>
     */
    private function posts(?array $scope): array
    {
        return SecurityPost::query()
            ->when($scope !== null, fn ($query) => $query->whereIn('unit_id', $scope))
            ->orderBy('name')
            ->get(['id', 'unit_id', 'post_code', 'name'])
            ->map(fn (SecurityPost $post) => [
                'value' => $post->id,
                'label' => $post->post_code.' — '.$post->name,
                'unit_id' => $post->unit_id,
            ])
            ->toArray();
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

        if (! in_array($user->role, ['security', 'admin'], true) || $user->unit_id === null) {
            return [];
        }

        return array_map('intval', $user->unit?->descendantIds() ?? [$user->unit_id]);
    }

    private function authorizeScope(Request $request, SecurityTeam $team): void
    {
        $team->loadMissing('securityPost');

        $scope = $this->unitScope($request);
        $unitId = $team->securityPost?->unit_id;

        if ($scope !== null && ($unitId === null || ! in_array((int) $unitId, $scope, true))) {
            abort(403);
        }
    }

    /**
     * Effective unit filter for the list (scope-safe).
     *
     * @param  array<int, int>|null  $scope
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
     * @param  array<int, int>|null  $scope
     */
    private function resolveFilterPostId(Request $request, ?array $scope, ?int $unitId): ?int
    {
        $postId = $this->queryInt($request, 'security_post_id');

        if ($postId === null) {
            return null;
        }

        $post = SecurityPost::query()->find($postId);

        if ($post === null) {
            return null;
        }

        if ($scope !== null && ! in_array((int) $post->unit_id, $scope, true)) {
            return null;
        }

        if ($unitId !== null && (int) $post->unit_id !== $unitId) {
            return null;
        }

        return $postId;
    }

    private function queryInt(Request $request, string $key): ?int
    {
        $raw = trim((string) $request->query($key, ''));

        return $raw !== '' && is_numeric($raw) ? (int) $raw : null;
    }
}
