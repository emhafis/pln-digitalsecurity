<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\SchedulePattern;
use App\Models\SchedulePeriod;
use App\Models\SchedulePeriodMember;
use App\Models\Security;
use App\Models\SecurityPost;
use App\Models\SecurityTeam;
use App\Models\Shift;
use App\Models\Unit;
use App\Services\ScheduleGenerationService;
use App\Support\AttendanceClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SchedulePeriodController extends Controller
{
    /**
     * Schedule period list.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $sortColumns = [
            'name' => 'schedule_periods.name',
            'status' => 'schedule_periods.status',
            'start_date' => 'schedule_periods.start_date',
            'end_date' => 'schedule_periods.end_date',
            'dibuat' => 'schedule_periods.created_at',
        ];

        $sort = (string) $request->query('sort', 'start_date');
        if (! array_key_exists($sort, $sortColumns) && $sort !== 'unit') {
            $sort = 'start_date';
        }
        $dir = $request->query('dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $status = (string) $request->query('status', '');
        if (! in_array($status, $this->statuses(), true)) {
            $status = '';
        }

        $startDate = $this->queryDate($request, 'start_date');
        $endDate = $this->queryDate($request, 'end_date');

        $query = SchedulePeriod::query()
            ->with(['unit', 'securityPost', 'securityTeam'])
            ->withCount(['members', 'schedules'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where('schedule_periods.name', 'like', "%{$search}%");
            })
            ->when($status !== '', function ($query) use ($status) {
                $query->where('schedule_periods.status', $status);
            })
            ->when($startDate !== null, function ($query) use ($startDate) {
                $query->whereDate('schedule_periods.start_date', '>=', $startDate);
            })
            ->when($endDate !== null, function ($query) use ($endDate) {
                $query->whereDate('schedule_periods.end_date', '<=', $endDate);
            });

        $scope = $this->unitScope($request);
        if ($scope !== null) {
            $query->whereIn('schedule_periods.unit_id', $scope);
        }

        $filterUnitId = $this->resolveFilterUnitId($request, $scope);
        if ($filterUnitId !== null) {
            $query->where('schedule_periods.unit_id', $filterUnitId);
        }

        $filterPostId = $this->queryInt($request, 'security_post_id');
        if ($filterPostId !== null) {
            $query->where('schedule_periods.security_post_id', $filterPostId);
        }

        $filterTeamId = $this->queryInt($request, 'security_team_id');
        if ($filterTeamId !== null) {
            $query->where('schedule_periods.security_team_id', $filterTeamId);
        }

        if ($sort === 'unit') {
            $query->leftJoin('units', 'schedule_periods.unit_id', '=', 'units.id')
                ->orderBy('units.unit_name', $dir)
                ->select('schedule_periods.*');
        } else {
            $query->orderBy($sortColumns[$sort], $dir);
        }

        $paginator = $query->paginate(50);

        return Inertia::render('Admin/SchedulePeriod/Index', [
            'periods' => $paginator->getCollection()->map(fn (SchedulePeriod $period) => $this->listItem($period)),
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
            'status' => $status,
            'start_date' => $startDate ?? '',
            'end_date' => $endDate ?? '',
            'units' => $this->units($scope),
            'posts' => $this->posts($scope),
            'teams' => $this->teamOptions($scope),
            'unit_id' => $filterUnitId ?? '',
            'security_post_id' => $filterPostId ?? '',
            'security_team_id' => $filterTeamId ?? '',
        ]);
    }

    /**
     * Schedule period create form.
     */
    public function create(Request $request): Response
    {
        $scope = $this->unitScope($request);

        if ($scope !== null && $request->user()?->unit_id === null) {
            abort(403);
        }

        return Inertia::render('Admin/SchedulePeriod/Create', [
            ...$this->referenceProps($scope),
            'previousPeriods' => $this->previousPeriods($scope),
            'filters' => $this->formFilters($request, $scope),
        ]);
    }

    /**
     * Store a newly created schedule period with its member snapshot.
     */
    public function store(Request $request): RedirectResponse
    {
        $scope = $this->unitScope($request);

        if ($scope !== null && $request->user()?->unit_id === null) {
            abort(403);
        }

        $this->normalizeInput($request);
        $validated = $this->validatePeriod($request, $scope);

        $team = SecurityTeam::with('securityPost.unit')->findOrFail($validated['security_team_id']);

        if (! $team->is_active) {
            throw ValidationException::withMessages([
                'security_team_id' => 'Regu nonaktif tidak dapat digunakan untuk periode jadwal baru.',
            ]);
        }

        SecurityTeam::query()->whereKey($team->id)->lockForUpdate()->first();

        $this->assertNoPeriodForTeamMonth(
            (int) $team->id,
            (int) ($validated['year'] ?? Carbon::parse($validated['start_date'])->year),
            (int) ($validated['month'] ?? Carbon::parse($validated['start_date'])->month)
        );

        $this->assertNoOverlap(
            (int) $team->id,
            $validated['start_date'],
            $validated['end_date']
        );

        $source = $this->copySource($request, $team, $scope);
        $members = $this->resolveMembers($request, $source, $team, (int) $validated['unit_id'], $validated['start_date']);

        $period = DB::transaction(function () use ($request, $validated, $members) {
            $period = SchedulePeriod::create([
                ...Arr::except($validated, ['members', 'copy_from_period_id']),
                'created_by' => $request->user()->id,
            ]);

            foreach ($members as $member) {
                $period->members()->create($member);
            }

            return $period;
        });

        return redirect()
            ->route('admin.schedule-period.show', $period->id)
            ->with('success', 'Periode jadwal berhasil ditambahkan.');
    }

    /**
     * Form for creating multiple schedule periods for several teams on one post.
     */
    public function bulkCreate(Request $request): Response
    {
        $scope = $this->unitScope($request);

        if ($scope !== null && $request->user()?->unit_id === null) {
            abort(403);
        }

        $selectedUnitId = $this->resolveSelectedUnitId($request, $scope);

        $units = $this->units($scope);
        $posts = [];
        $teams = [];
        $patterns = [];

        if ($selectedUnitId !== null) {
            $posts = array_values(array_filter(
                $this->posts($scope),
                fn ($post) => (int) $post['unit_id'] === $selectedUnitId
            ));

            $patterns = $this->patterns([$selectedUnitId]);

            $selectedPostId = $this->resolveSelectedPostId($request, $posts);

            if ($selectedPostId !== null) {
                $teams = array_values(array_filter(
                    $this->teamOptions($scope),
                    fn ($team) => (int) $team['security_post_id'] === $selectedPostId
                        && (bool) $team['is_active'] === true
                ));
            } else {
                $selectedPostId = null;
            }
        } else {
            $selectedPostId = null;
        }

        return Inertia::render('Admin/SchedulePeriod/BulkCreate', [
            'units' => $units,
            'posts' => $posts,
            'teams' => $teams,
            'patterns' => $patterns,
            'today' => AttendanceClock::todayWib(),
            'filters' => [
                'unit_id' => $selectedUnitId ?? '',
                'security_post_id' => $selectedPostId ?? '',
                'start_date' => $this->queryDate($request, 'start_date') ?? $request->query('start_date', ''),
            ],
        ]);
    }

    /**
     * Create multiple schedule periods for several teams on one post.
     */
    public function bulkStore(Request $request): RedirectResponse
    {
        $scope = $this->unitScope($request);

        if ($scope !== null && $request->user()?->unit_id === null) {
            abort(403);
        }

        $this->normalizeInput($request);

        $validated = $request->validate([
            'unit_id' => array_merge($this->unitIdRules($scope), ['required']),
            'security_post_id' => [
                'required',
                'integer',
                Rule::exists('security_posts', 'id')->where(
                    fn ($query) => $query->where('unit_id', (int) $request->input('unit_id'))
                ),
            ],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in($this->statuses())],
            'teams' => ['required', 'array', 'min:1'],
            'teams.*.security_team_id' => ['required', 'integer', 'distinct'],
            'teams.*.members' => ['sometimes', 'array'],
            'teams.*.members.*.security_id' => ['required', 'integer', 'exists:securities,id'],
            'teams.*.members.*.schedule_pattern_id' => ['nullable', 'integer', 'exists:schedule_patterns,id'],
            'teams.*.members.*.start_offset' => ['nullable', 'integer', 'min:0'],
            'teams.*.members.*.rotation_day' => ['nullable', 'integer', 'min:1'],
        ]);

        $post = SecurityPost::query()->findOrFail((int) $validated['security_post_id']);

        if ($scope !== null && ! in_array((int) $post->unit_id, $scope, true)) {
            abort(403);
        }

        if ((int) $post->unit_id !== (int) $validated['unit_id']) {
            throw ValidationException::withMessages([
                'security_post_id' => 'Pos jaga harus berada pada unit yang sama dengan periode.',
            ]);
        }

        $teamIds = collect($validated['teams'])
            ->pluck('security_team_id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $teams = SecurityTeam::query()
            ->with('securityPost.unit')
            ->whereIn('id', $teamIds)
            ->get()
            ->keyBy('id');

        $preparedTeams = [];

        foreach ($validated['teams'] as $index => $teamData) {
            $teamId = (int) $teamData['security_team_id'];
            $team = $teams->get($teamId);

            if ($team === null) {
                throw ValidationException::withMessages([
                    "teams.{$index}.security_team_id" => 'Regu tidak ditemukan.',
                ]);
            }

            if ((int) $team->security_post_id !== (int) $post->id) {
                throw ValidationException::withMessages([
                    "teams.{$index}.security_team_id" => 'Regu harus berada pada pos jaga yang dipilih.',
                ]);
            }

            if (! $team->is_active) {
                throw ValidationException::withMessages([
                    "teams.{$index}.security_team_id" => 'Regu nonaktif tidak dapat digunakan untuk periode jadwal baru.',
                ]);
            }

            SecurityTeam::query()->whereKey($teamId)->lockForUpdate()->first();

            $this->assertNoPeriodForTeamMonth(
                (int) $team->id,
                (int) ($validated['year'] ?? Carbon::parse($validated['start_date'])->year),
                (int) ($validated['month'] ?? Carbon::parse($validated['start_date'])->month)
            );

            $this->assertNoOverlap(
                (int) $team->id,
                $validated['start_date'],
                $validated['end_date']
            );

            $rows = array_key_exists('members', $teamData) && is_array($teamData['members'])
                ? $teamData['members']
                : $this->activeTeamMemberRows($team, $validated['start_date']);

            foreach ($rows as $memberIndex => $row) {
                if (isset($row['rotation_day']) && $row['rotation_day'] !== null && $row['rotation_day'] !== '') {
                    $rotationDay = (int) $row['rotation_day'];

                    if ($rotationDay < 1) {
                        throw ValidationException::withMessages([
                            "teams.{$index}.members.{$memberIndex}.start_offset" => 'Hari Rotasi awal minimal 1.',
                        ]);
                    }

                    $rows[$memberIndex]['start_offset'] = $rotationDay - 1;
                } elseif (! array_key_exists('start_offset', $row) || $row['start_offset'] === null || $row['start_offset'] === '') {
                    $rows[$memberIndex]['start_offset'] = 0;
                } else {
                    $rows[$memberIndex]['start_offset'] = (int) $row['start_offset'];
                }
            }

            $members = $this->validateMemberRows(
                $rows,
                (int) $validated['unit_id'],
                $team
            );

            $preparedTeams[] = [
                'team' => $team,
                'members' => $members,
            ];
        }

        try {
            $periods = DB::transaction(function () use ($request, $validated, $preparedTeams) {
                $created = [];

                foreach ($preparedTeams as $prepared) {
                    $team = $prepared['team'];

                    SecurityTeam::query()->whereKey($team->id)->lockForUpdate()->first();

                    $this->assertNoPeriodForTeamMonth(
                        (int) $team->id,
                        (int) ($validated['year'] ?? Carbon::parse($validated['start_date'])->year),
                        (int) ($validated['month'] ?? Carbon::parse($validated['start_date'])->month)
                    );

                    $this->assertNoOverlap(
                        (int) $team->id,
                        $validated['start_date'],
                        $validated['end_date']
                    );

                    $period = SchedulePeriod::create([
                        'unit_id' => $validated['unit_id'],
                        'security_post_id' => $validated['security_post_id'],
                        'security_team_id' => $team->id,
                        'name' => $validated['name'],
                        'start_date' => $validated['start_date'],
                        'end_date' => $validated['end_date'],
                        'status' => $validated['status'],
                        'created_by' => $request->user()->id,
                    ]);

                    foreach ($prepared['members'] as $member) {
                        $period->members()->create($member);
                    }

                    $created[] = $period;
                }

                return $created;
            });
        } catch (QueryException $exception) {
            return back()
                ->withInput()
                ->with('error', 'Tidak dapat membuat periode jadwal massal: '.$exception->getMessage());
        }

        return redirect()
            ->route('admin.schedule-period.index')
            ->with('success', count($periods).' periode jadwal berhasil dibuat.')
            ->with('bulk_created', collect($periods)->map(fn (SchedulePeriod $period) => [
                'id' => $period->id,
                'name' => $period->name,
                'team_name' => $period->securityTeam?->name ?? '-',
                'start_date' => $period->start_date?->toDateString(),
                'end_date' => $period->end_date?->toDateString(),
                'status' => $period->status,
                'status_label' => $this->statusLabel($period->status),
                'show_url' => route('admin.schedule-period.show', $period->id),
            ])->values()->all());
    }

    /**
     * Schedule period detail.
     */
    public function show(Request $request, int $id): Response
    {
        $period = SchedulePeriod::query()
            ->with([
                'unit',
                'securityPost',
                'securityTeam',
                'members.security',
                'members.schedulePattern',
            ])
            ->withCount('schedules')
            ->findOrFail($id);

        $this->authorizeScope($request, $period);

        $schedules = $period->schedules()
            ->with([
                'security:id,full_name,registration_number',
                'shift:id,name,code,start_time,end_time,end_day_offset',
                'updater:id,name',
            ])
            ->orderBy('date')
            ->orderBy('security_id')
            ->get()
            ->map(fn (Schedule $schedule) => [
                'id' => $schedule->id,
                'date' => $schedule->date->toDateString(),
                'date_label' => $schedule->date->format('d M Y'),
                'security_id' => $schedule->security_id,
                'full_name' => $schedule->security?->full_name ?? '-',
                'shift_id' => $schedule->shift_id,
                'shift_name' => $schedule->shift?->name,
                'shift_time' => $this->shiftTime($schedule->shift),
                'pattern_day_order' => $schedule->pattern_day_order,
                'source' => $schedule->source,
                'notes' => $schedule->notes,
                'updated_by_name' => $schedule->updater?->name,
                'updated_at' => AttendanceClock::formatWib($schedule->updated_at),
            ]);

        $shiftOptions = Shift::query()
            ->where('unit_id', $period->unit_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'start_time', 'end_time', 'end_day_offset'])
            ->map(fn (Shift $shift) => [
                'value' => $shift->id,
                'label' => $shift->name.($this->shiftTime($shift) ? ' ('.$this->shiftTime($shift).')' : ''),
            ]);

        return Inertia::render('Admin/SchedulePeriod/Show', [
            'period' => $this->transform($period),
            'schedules' => $schedules,
            'shiftOptions' => $shiftOptions,
            'isLocked' => $period->status === SchedulePeriod::STATUS_COMPLETED,
        ]);
    }

    /**
     * Manually adjust the shift of one schedule row in this period.
     */
    public function updateSchedule(Request $request, int $id, int $scheduleId): RedirectResponse
    {
        $period = SchedulePeriod::findOrFail($id);
        $this->authorizeScope($request, $period);

        if ($period->status === SchedulePeriod::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'schedule' => 'Periode sudah selesai sehingga jadwal tidak dapat diubah.',
            ]);
        }

        $validated = $request->validate([
            'shift_id' => [
                'required',
                'integer',
                Rule::exists('shifts', 'id')
                    ->where('unit_id', $period->unit_id)
                    ->where('is_active', true),
            ],
            'notes' => ['required', 'string', 'max:1000'],
        ], [
            'shift_id.exists' => 'Shift pengganti harus aktif dan berasal dari unit periode.',
            'notes.required' => 'Alasan perubahan wajib diisi.',
        ]);

        DB::transaction(function () use ($request, $period, $scheduleId, $validated) {
            // Keep the lock order consistent with the generator: period row
            // first, then the schedule row.
            $lockedPeriod = SchedulePeriod::query()
                ->whereKey($period->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPeriod->status === SchedulePeriod::STATUS_COMPLETED) {
                throw ValidationException::withMessages([
                    'schedule' => 'Periode sudah selesai sehingga jadwal tidak dapat diubah.',
                ]);
            }

            $schedule = Schedule::query()
                ->where('schedule_period_id', $lockedPeriod->id)
                ->lockForUpdate()
                ->findOrFail($scheduleId);

            $isSnapshotMember = SchedulePeriodMember::query()
                ->whereKey($schedule->schedule_period_member_id)
                ->where('schedule_period_id', $lockedPeriod->id)
                ->where('security_id', $schedule->security_id)
                ->exists();

            if (! $isSnapshotMember) {
                throw ValidationException::withMessages([
                    'schedule' => 'Satpam pada jadwal ini bukan anggota snapshot periode.',
                ]);
            }

            if ((int) $schedule->shift_id === (int) $validated['shift_id']) {
                throw ValidationException::withMessages([
                    'shift_id' => 'Shift pengganti sama dengan shift saat ini.',
                ]);
            }

            // ponytail: only shift/source/notes/updated_by change; date and security stay fixed, so the
            // (security_id, date) unique index cannot be violated here.
            $schedule->update([
                'shift_id' => (int) $validated['shift_id'],
                'source' => Schedule::SOURCE_ADJUSTMENT,
                'notes' => trim($validated['notes']),
                'updated_by' => $request->user()->id,
            ]);
        });

        return redirect()
            ->route('admin.schedule-period.show', $period->id)
            ->with('success', 'Jadwal berhasil disesuaikan secara manual.');
    }

    private function shiftTime(?Shift $shift): ?string
    {
        if ($shift === null || ! $shift->start_time || ! $shift->end_time) {
            return null;
        }

        return substr($shift->start_time, 0, 5).' - '.substr($shift->end_time, 0, 5)
            .($shift->end_day_offset ? ' (+1 hari)' : '');
    }

    /**
     * Preview automatic schedule generation for a period.
     */
    public function generatePreview(
        Request $request,
        int $id,
        ScheduleGenerationService $generator
    ): Response {
        $period = SchedulePeriod::query()
            ->with([
                'unit',
                'securityPost',
                'securityTeam',
                'members.security',
                'members.schedulePattern',
            ])
            ->withCount('schedules')
            ->findOrFail($id);

        $this->authorizeScope($request, $period);

        $mode = (string) $request->query('mode', ScheduleGenerationService::MODE_FILL);

        return Inertia::render('Admin/SchedulePeriod/Generate', [
            'period' => $this->transform($period),
            'preview' => $generator->preview($period, $mode),
        ]);
    }

    /**
     * Generate or regenerate schedules for a period.
     */
    public function generate(
        Request $request,
        int $id,
        ScheduleGenerationService $generator
    ): RedirectResponse {
        $validated = $request->validate([
            'mode' => [
                'required',
                Rule::in([
                    ScheduleGenerationService::MODE_FILL,
                    ScheduleGenerationService::MODE_REGENERATE,
                ]),
            ],
        ]);

        $period = SchedulePeriod::findOrFail($id);
        $this->authorizeScope($request, $period);

        $result = $generator->generate(
            $period,
            $validated['mode'],
            $request->user()
        );

        $message = $validated['mode'] === ScheduleGenerationService::MODE_REGENERATE
            ? sprintf(
                'Regenerate selesai: %d jadwal dibuat, %d diperbarui, %d dihapus, %d jadwal manual dipertahankan.',
                $result['created'],
                $result['updated'],
                $result['deleted'],
                $result['manual_preserved']
            )
            : sprintf(
                'Generate selesai: %d jadwal dibuat, %d dilewati karena sudah tersedia, %d jadwal manual dipertahankan.',
                $result['created'],
                $result['skipped'],
                $result['manual_preserved']
            );

        return redirect()
            ->route('admin.schedule-period.generate.preview', [
                'id' => $period->id,
                'mode' => $validated['mode'],
            ])
            ->with('success', $message)
            ->with('generation_result', $result);
    }

    /**
     * Schedule period edit form.
     */
    public function edit(Request $request, int $id): Response
    {
        $period = SchedulePeriod::query()
            ->with([
                'unit',
                'securityPost',
                'securityTeam',
                'members.security',
                'members.schedulePattern',
            ])
            ->withCount('schedules')
            ->findOrFail($id);

        $this->authorizeScope($request, $period);

        $scope = $this->unitScope($request);

        return Inertia::render('Admin/SchedulePeriod/Edit', [
            'period' => $this->transform($period),
            ...$this->referenceProps($scope),
            'previousPeriods' => $this->previousPeriods($scope, (int) $period->id),
        ]);
    }

    /**
     * Update schedule period status inline from list.
     */
    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $period = SchedulePeriod::findOrFail($id);
        $this->authorizeScope($request, $period);

        $validated = $request->validate([
            'status' => ['required', Rule::in($this->statuses())],
        ]);

        if ($validated['status'] === $period->status) {
            return back();
        }

        $period->update([
            'status' => $validated['status'],
        ]);

        return back()->with('success', 'Status periode jadwal berhasil diperbarui.');
    }

    /**
     * Update an existing schedule period.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $period = SchedulePeriod::findOrFail($id);
        $this->authorizeScope($request, $period);

        $scope = $this->unitScope($request);

        $this->normalizeInput($request);
        $validated = $this->validatePeriod($request, $scope, (int) $period->id);

        $hasSchedules = $period->schedules()->exists();

        if ($hasSchedules) {
            $this->assertLockedFieldsUnchanged($period, $validated);
            $this->assertLockedMembersUnchanged($period, $request);

            DB::transaction(function () use ($period, $validated) {
                $period->update([
                    'name' => $validated['name'],
                    'status' => $validated['status'],
                ]);
            });

            return redirect()
                ->route('admin.schedule-period.show', $period->id)
                ->with('success', 'Periode jadwal berhasil diperbarui.');
        }

        $team = SecurityTeam::with('securityPost.unit')->findOrFail($validated['security_team_id']);

        if (
            (int) $team->id !== (int) $period->security_team_id
            && ! $team->is_active
        ) {
            throw ValidationException::withMessages([
                'security_team_id' => 'Regu nonaktif tidak dapat digunakan untuk periode jadwal baru.',
            ]);
        }

        SecurityTeam::query()->whereKey($team->id)->lockForUpdate()->first();

        $this->assertNoPeriodForTeamMonth(
            (int) $team->id,
            (int) ($validated['year'] ?? Carbon::parse($validated['start_date'])->year),
            (int) ($validated['month'] ?? Carbon::parse($validated['start_date'])->month),
            (int) $period->id
        );

        $this->assertNoOverlap(
            (int) $team->id,
            $validated['start_date'],
            $validated['end_date'],
            (int) $period->id
        );

        $source = $this->copySource($request, $team, $scope);
        $members = null;

        if ($request->exists('members') || $source !== null) {
            $members = $this->resolveMembers($request, $source, $team, (int) $validated['unit_id'], $validated['start_date']);
        }

        DB::transaction(function () use ($period, $validated, $members) {
            $period->update([
                'unit_id' => $validated['unit_id'],
                'security_post_id' => $validated['security_post_id'],
                'security_team_id' => $validated['security_team_id'],
                'name' => $validated['name'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'status' => $validated['status'],
            ]);

            if ($members !== null) {
                $period->members()->delete();

                foreach ($members as $member) {
                    $period->members()->create($member);
                }
            }
        });

        return redirect()
            ->route('admin.schedule-period.show', $period->id)
            ->with('success', 'Periode jadwal berhasil diperbarui.');
    }

    /**
     * @param  array<int, int>|null  $scope
     * @return array<string, mixed>
     */
    private function referenceProps(?array $scope): array
    {
        return [
            'units' => $this->units($scope),
            'posts' => $this->posts($scope),
            'teams' => $this->teamOptions($scope),
            'patterns' => $this->patterns($scope),
            'securities' => $this->securities($scope),
            'today' => AttendanceClock::todayWib(),
        ];
    }

    /**
     * @param  array<int, int>|null  $scope
     * @return array<string, mixed>
     */
    private function formFilters(Request $request, ?array $scope): array
    {
        $unitId = $this->queryInt($request, 'unit_id');
        if ($unitId !== null && $scope !== null && ! in_array($unitId, $scope, true)) {
            $unitId = null;
        }

        $postId = $this->queryInt($request, 'security_post_id');
        $teamId = $this->queryInt($request, 'security_team_id');
        $startDate = $this->queryDate($request, 'start_date');

        if ($postId !== null) {
            $post = SecurityPost::query()->find($postId);
            $allowed = $post !== null && ($scope === null || in_array((int) $post->unit_id, $scope, true));
            if (! $allowed) {
                $postId = null;
            } else {
                $unitId = $unitId ?? (int) $post->unit_id;
            }
        }

        if ($teamId !== null) {
            $team = SecurityTeam::with('securityPost')->find($teamId);
            $allowed = $team !== null
                && $team->securityPost !== null
                && ($scope === null || in_array((int) $team->securityPost->unit_id, $scope, true))
                && ($postId === null || (int) $team->security_post_id === $postId);
            if (! $allowed) {
                $teamId = null;
            } else {
                $postId = $postId ?? (int) $team->security_post_id;
                $unitId = $unitId ?? (int) $team->securityPost->unit_id;
            }
        }

        return [
            'unit_id' => $unitId ?? '',
            'security_post_id' => $postId ?? '',
            'security_team_id' => $teamId ?? '',
            'start_date' => $startDate ?? '',
        ];
    }

    /**
     * Validate the base period fields.
     *
     * @param  array<int, int>|null  $scope
     * @return array<string, mixed>
     */
    private function validatePeriod(Request $request, ?array $scope, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'unit_id' => array_merge($this->unitIdRules($scope), ['required']),
            'security_post_id' => [
                'required',
                'integer',
                Rule::exists('security_posts', 'id')->where(
                    fn ($query) => $query->where('unit_id', (int) $request->input('unit_id'))
                ),
            ],
            'security_team_id' => [
                'required',
                'integer',
                Rule::exists('security_teams', 'id')->where(
                    fn ($query) => $query->where('security_post_id', (int) $request->input('security_post_id'))
                ),
            ],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in($this->statuses())],
            'copy_from_period_id' => ['nullable', 'integer', 'exists:schedule_periods,id'],
            'members' => ['sometimes', 'array'],
            'members.*.security_id' => ['required', 'integer', 'distinct', 'exists:securities,id'],
            'members.*.schedule_pattern_id' => ['nullable', 'integer', 'exists:schedule_patterns,id'],
            'members.*.start_offset' => ['nullable', 'integer', 'min:0'],
        ]);

        return $validated;
    }

    /**
     * @return array<int, string>
     */
    private function statuses(): array
    {
        return [
            SchedulePeriod::STATUS_DRAFT,
            SchedulePeriod::STATUS_ACTIVE,
            SchedulePeriod::STATUS_COMPLETED,
        ];
    }

    private function copySource(Request $request, SecurityTeam $team, ?array $scope): ?SchedulePeriod
    {
        $raw = $request->input('copy_from_period_id');

        if ($raw === null || $raw === '') {
            return null;
        }

        $source = SchedulePeriod::query()
            ->with(['members.security', 'members.schedulePattern'])
            ->find((int) $raw);

        if ($source === null) {
            throw ValidationException::withMessages([
                'copy_from_period_id' => 'Periode sumber tidak ditemukan.',
            ]);
        }

        if ($scope !== null && ! in_array((int) $source->unit_id, $scope, true)) {
            throw ValidationException::withMessages([
                'copy_from_period_id' => 'Periode sumber berada di luar unit yang dapat diakses.',
            ]);
        }

        if ((int) $source->security_team_id !== (int) $team->id) {
            throw ValidationException::withMessages([
                'copy_from_period_id' => 'Snapshot hanya dapat disalin dari periode dengan regu yang sama.',
            ]);
        }

        return $source;
    }

    /**
     * Resolve the final snapshot rows for store/update.
     *
     * @return array<int, array{security_id: int, schedule_pattern_id: int|null, start_offset: int}>
     */
    private function resolveMembers(
        Request $request,
        ?SchedulePeriod $source,
        SecurityTeam $team,
        int $unitId,
        string $startDate
    ): array {
        if ($request->exists('members')) {
            return $this->validateMemberRows(
                (array) $request->input('members', []),
                $unitId,
                $team
            );
        }

        if ($source !== null) {
            $rows = $source->members->map(fn ($member) => [
                'security_id' => $member->security_id,
                'schedule_pattern_id' => $member->schedule_pattern_id,
                'start_offset' => $member->start_offset,
            ])->all();

            return $this->validateMemberRows($rows, $unitId, $team);
        }

        return $this->activeTeamMemberRows($team, $startDate);
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array{security_id: int, schedule_pattern_id: int|null, start_offset: int}>
     */
    private function validateMemberRows(array $rows, int $unitId, SecurityTeam $team): array
    {
        $team->loadMissing('securityPost.unit');

        $securityIds = collect($rows)
            ->pluck('security_id')
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        $patternIds = collect($rows)
            ->pluck('schedule_pattern_id')
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        $securities = Security::query()
            ->with('user:id,unit_id')
            ->whereIn('id', $securityIds)
            ->get()
            ->keyBy('id');

        $patterns = SchedulePattern::query()
            ->whereIn('id', $patternIds)
            ->get()
            ->keyBy('id');

        $result = [];
        $seen = [];

        foreach (array_values($rows) as $index => $row) {
            $securityId = (int) ($row['security_id'] ?? 0);
            $patternId = $row['schedule_pattern_id'] ?? null;
            $patternId = $patternId === null || $patternId === '' ? null : (int) $patternId;
            $startOffset = (int) ($row['start_offset'] ?? 0);

            if ($securityId < 1 || ! $securities->has($securityId)) {
                throw ValidationException::withMessages([
                    "members.{$index}.security_id" => 'Satpam tidak ditemukan.',
                ]);
            }

            if (isset($seen[$securityId])) {
                throw ValidationException::withMessages([
                    "members.{$index}.security_id" => 'Satpam tidak boleh dipilih lebih dari sekali.',
                ]);
            }
            $seen[$securityId] = true;

            if (! $team->acceptsSecurity($securities->get($securityId))) {
                throw ValidationException::withMessages([
                    "members.{$index}.security_id" => 'Satpam harus berada dalam unit pos jaga regu.',
                ]);
            }

            $exactUnitId = null;
            $authUser = auth()->user();
            if ($authUser !== null && $authUser->role !== 'superadmin' && $authUser->unit_id !== null) {
                $exactUnitId = (int) $authUser->unit_id;
            }

            if ($exactUnitId !== null && (int) ($securities->get($securityId)->user?->unit_id ?? 0) !== $exactUnitId) {
                throw ValidationException::withMessages([
                    "members.{$index}.security_id" => 'Satpam berada di luar unit yang dapat diakses.',
                ]);
            }

            if ($patternId !== null) {
                $pattern = $patterns->get($patternId);

                if ($pattern === null) {
                    throw ValidationException::withMessages([
                        "members.{$index}.schedule_pattern_id" => 'Pola rotasi tidak ditemukan.',
                    ]);
                }

                if ((int) $pattern->unit_id !== $unitId) {
                    throw ValidationException::withMessages([
                        "members.{$index}.schedule_pattern_id" => 'Pola rotasi harus berada pada unit yang sama dengan periode.',
                    ]);
                }

                if (! $pattern->is_active) {
                    throw ValidationException::withMessages([
                        "members.{$index}.schedule_pattern_id" => 'Pola rotasi nonaktif tidak dapat digunakan untuk periode baru.',
                    ]);
                }

                if ($startOffset < 0 || $startOffset >= (int) $pattern->cycle_length) {
                    throw ValidationException::withMessages([
                        "members.{$index}.start_offset" => 'Posisi awal rotasi harus berada di antara 0 dan jumlah hari siklus - 1.',
                    ]);
                }
            } elseif ($startOffset !== 0) {
                throw ValidationException::withMessages([
                    "members.{$index}.start_offset" => 'Posisi awal rotasi hanya dapat diisi jika pola rotasi dipilih.',
                ]);
            }

            $result[] = [
                'security_id' => $securityId,
                'schedule_pattern_id' => $patternId,
                'start_offset' => $startOffset,
            ];
        }

        return $result;
    }

    /**
     * Snapshot rows for team members who are active on the period start date.
     *
     * @return array<int, array{security_id: int, schedule_pattern_id: int|null, start_offset: int}>
     */
    private function activeTeamMemberRows(SecurityTeam $team, string $startDate): array
    {
        $team->loadMissing('securityPost.unit');

        return $team->members()
            ->with(['security:id,user_id,full_name,registration_number', 'security.user:id,unit_id'])
            ->whereDate('joined_at', '<=', $startDate)
            ->where(function ($query) use ($startDate) {
                $query->whereNull('left_at')
                    ->orWhereDate('left_at', '>=', $startDate);
            })
            ->get()
            ->filter(fn ($member) => $member->security !== null && $team->acceptsSecurity($member->security))
            ->sortBy(fn ($member) => $member->security->full_name)
            ->map(fn ($member) => [
                'security_id' => (int) $member->security_id,
                'schedule_pattern_id' => null,
                'start_offset' => 0,
            ])
            ->values()
            ->all();
    }

    private function assertNoPeriodForTeamMonth(
        int $teamId,
        int $year,
        int $month,
        ?int $ignoreId = null
    ): void {
        $exists = SchedulePeriod::query()
            ->where('security_team_id', $teamId)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get()
            ->contains(fn (SchedulePeriod $period) => $period->start_date !== null
                && (int) $period->start_date->year === $year
                && (int) $period->start_date->month === $month);

        if ($exists) {
            throw ValidationException::withMessages([
                'start_date' => 'Regu ini sudah memiliki periode jadwal pada bulan dan tahun tersebut.',
            ]);
        }
    }

    private function assertNoOverlap(
        int $teamId,
        string $startDate,
        string $endDate,
        ?int $ignoreId = null
    ): void {
        $exists = SchedulePeriod::query()
            ->where('security_team_id', $teamId)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->overlapping($startDate, $endDate)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'start_date' => 'Periode jadwal untuk regu ini sudah ada pada rentang tanggal tersebut.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assertLockedFieldsUnchanged(SchedulePeriod $period, array $validated): void
    {
        $locked = [
            'unit_id' => [(int) $validated['unit_id'], (int) $period->unit_id],
            'security_post_id' => [(int) $validated['security_post_id'], (int) $period->security_post_id],
            'security_team_id' => [(int) $validated['security_team_id'], (int) $period->security_team_id],
            'start_date' => [$validated['start_date'], $period->start_date->toDateString()],
            'end_date' => [$validated['end_date'], $period->end_date->toDateString()],
        ];

        $messages = [
            'unit_id' => 'Unit periode tidak dapat diubah karena periode sudah memiliki jadwal.',
            'security_post_id' => 'Pos jaga tidak dapat diubah karena periode sudah memiliki jadwal.',
            'security_team_id' => 'Regu tidak dapat diubah karena periode sudah memiliki jadwal.',
            'start_date' => 'Tanggal mulai tidak dapat diubah karena periode sudah memiliki jadwal.',
            'end_date' => 'Tanggal selesai tidak dapat diubah karena periode sudah memiliki jadwal.',
        ];

        foreach ($locked as $field => [$submitted, $current]) {
            if ((string) $submitted !== (string) $current) {
                throw ValidationException::withMessages([$field => $messages[$field]]);
            }
        }
    }

    private function assertLockedMembersUnchanged(SchedulePeriod $period, Request $request): void
    {
        if (! $request->exists('members')) {
            return;
        }

        $submitted = $this->memberSignature((array) $request->input('members', []));
        $current = $this->memberSignature(
            $period->members()
                ->get(['security_id', 'schedule_pattern_id', 'start_offset'])
                ->map(fn ($member) => [
                    'security_id' => $member->security_id,
                    'schedule_pattern_id' => $member->schedule_pattern_id,
                    'start_offset' => $member->start_offset,
                ])
                ->all()
        );

        if ($submitted !== $current) {
            throw ValidationException::withMessages([
                'members' => 'Anggota periode tidak dapat diubah karena periode sudah memiliki jadwal.',
            ]);
        }
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array{security_id: int, schedule_pattern_id: int|null, start_offset: int}>
     */
    private function memberSignature(array $rows): array
    {
        $signature = [];

        foreach ($rows as $row) {
            $patternId = $row['schedule_pattern_id'] ?? null;

            $signature[] = [
                'security_id' => (int) ($row['security_id'] ?? 0),
                'schedule_pattern_id' => $patternId === null || $patternId === '' ? null : (int) $patternId,
                'start_offset' => (int) ($row['start_offset'] ?? 0),
            ];
        }

        usort($signature, fn ($a, $b) => $a['security_id'] <=> $b['security_id']);

        return $signature;
    }

    private function normalizeInput(Request $request): void
    {
        $month = $request->input('month');
        $year = $request->input('year');

        if ($month !== null && $month !== '' && $year !== null && $year !== '') {
            $month = (int) $month;
            $year = (int) $year;
            $start = Carbon::create($year, $month, 1);
            $end = $start->copy()->endOfMonth();

            $request->merge([
                'month' => $month,
                'year' => $year,
                'name' => trim((string) $request->input('name')) ?: $start->translatedFormat('F Y'),
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ]);

            return;
        }

        $request->merge([
            'name' => trim((string) $request->input('name')),
        ]);
    }

    private function listItem(SchedulePeriod $period): array
    {
        return [
            'id' => $period->id,
            'name' => $period->name,
            'unit_id' => $period->unit_id,
            'unit_name' => $period->unit?->unit_name ?? '-',
            'security_post_id' => $period->security_post_id,
            'post_name' => $period->securityPost?->name ?? '-',
            'security_team_id' => $period->security_team_id,
            'team_name' => $period->securityTeam?->name ?? '-',
            'start_date' => $period->start_date?->format('Y-m-d'),
            'end_date' => $period->end_date?->format('Y-m-d'),
            'start_date_label' => $period->start_date?->format('d M Y'),
            'end_date_label' => $period->end_date?->format('d M Y'),
            'members_count' => (int) $period->members_count,
            'schedules_count' => (int) $period->schedules_count,
            'is_active' => $period->status === SchedulePeriod::STATUS_ACTIVE,
            'status' => $period->status,
            'status_label' => $this->statusLabel($period->status),
        ];
    }

    private function transform(SchedulePeriod $period): array
    {
        return [
            'id' => $period->id,
            'unit_id' => $period->unit_id,
            'unit_name' => $period->unit?->unit_name ?? '-',
            'security_post_id' => $period->security_post_id,
            'post_name' => $period->securityPost?->name ?? '-',
            'security_team_id' => $period->security_team_id,
            'team_name' => $period->securityTeam?->name ?? '-',
            'name' => $period->name,
            'start_date' => $period->start_date?->format('Y-m-d'),
            'end_date' => $period->end_date?->format('Y-m-d'),
            'start_date_label' => $period->start_date?->format('d M Y'),
            'end_date_label' => $period->end_date?->format('d M Y'),
            'status' => $period->status,
            'status_label' => $this->statusLabel($period->status),
            'schedules_count' => (int) $period->schedules_count,
            'has_schedules' => $period->schedules_count > 0,
            'members' => $period->members->map(fn ($member) => [
                'id' => $member->id,
                'security_id' => $member->security_id,
                'full_name' => $member->security?->full_name ?? '-',
                'registration_number' => $member->security?->registration_number,
                'schedule_pattern_id' => $member->schedule_pattern_id,
                'pattern_name' => $member->schedulePattern?->name,
                'pattern_cycle_length' => $member->schedulePattern?->cycle_length,
                'start_offset' => (int) $member->start_offset,
            ])->values(),
            'created_at' => AttendanceClock::formatWib($period->created_at),
            'updated_at' => AttendanceClock::formatWib($period->updated_at),
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            SchedulePeriod::STATUS_DRAFT => 'Draft',
            SchedulePeriod::STATUS_ACTIVE => 'Aktif',
            SchedulePeriod::STATUS_COMPLETED => 'Selesai',
            default => ucfirst($status),
        };
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
     * @param  array<int, int>|null  $scope
     * @return array<int, array<string, mixed>>
     */
    private function teamOptions(?array $scope): array
    {
        return SecurityTeam::query()
            ->when($scope !== null, function ($query) use ($scope) {
                $query->whereHas('securityPost', fn ($postQuery) => $postQuery->whereIn('unit_id', $scope));
            })
            ->with([
                'securityPost:id,unit_id',
                'members.security:id,user_id,full_name,registration_number',
            ])
            ->orderBy('name')
            ->get(['id', 'security_post_id', 'name', 'code', 'is_active'])
            ->map(fn (SecurityTeam $team) => [
                'value' => $team->id,
                'label' => $team->code.' — '.$team->name.($team->is_active ? '' : ' (Nonaktif)'),
                'security_post_id' => $team->security_post_id,
                'unit_id' => $team->securityPost?->unit_id,
                'is_active' => $team->is_active,
                'memberships' => $team->members->map(fn ($member) => [
                    'security_id' => $member->security_id,
                    'joined_at' => $member->joined_at?->format('Y-m-d'),
                    'left_at' => $member->left_at?->format('Y-m-d'),
                    'full_name' => $member->security?->full_name ?? '-',
                    'registration_number' => $member->security?->registration_number,
                ])->values(),
            ])
            ->toArray();
    }

    /**
     * @param  array<int, int>|null  $scope
     * @return array<int, array{value: int, label: string, unit_id: int, cycle_length: int}>
     */
    private function patterns(?array $scope): array
    {
        return SchedulePattern::query()
            ->when($scope !== null, fn ($query) => $query->whereIn('unit_id', $scope))
            ->where('is_active', true)
            ->with('details.shift:id,name,code,start_time,end_time,end_day_offset,type')
            ->orderBy('name')
            ->get(['id', 'unit_id', 'name', 'cycle_length'])
            ->map(fn (SchedulePattern $pattern) => [
                'value' => $pattern->id,
                'label' => $pattern->name.' ('.$pattern->cycle_length.' hari)',
                'unit_id' => $pattern->unit_id,
                'cycle_length' => (int) $pattern->cycle_length,
                'details' => $pattern->details->map(fn ($detail) => [
                    'day_order' => (int) $detail->day_order,
                    'shift_id' => (int) $detail->shift_id,
                    'shift_name' => $detail->shift?->name,
                    'shift_code' => $detail->shift?->code,
                    'shift_type' => $detail->shift?->type,
                    'shift_time' => $detail->shift !== null && $detail->shift->type !== Shift::TYPE_DAY_OFF
                        ? substr((string) $detail->shift->start_time, 0, 5).' - '.substr((string) $detail->shift->end_time, 0, 5).($detail->shift->end_day_offset ? ' (+1 hari)' : '')
                        : null,
                ])->values(),
            ])
            ->toArray();
    }

    /**
     * @param  array<int, int>|null  $scope
     * @return array<int, array<string, mixed>>
     */
    private function securities(?array $scope): array
    {
        $exactUnitId = null;
        $user = auth()->user();
        if ($scope !== null && $user !== null && $user->role !== 'superadmin' && $user->unit_id !== null) {
            $exactUnitId = (int) $user->unit_id;
        }

        return Security::query()
            ->when($exactUnitId !== null, function ($query) use ($exactUnitId) {
                $query->whereHas('user', fn ($userQuery) => $userQuery->where('unit_id', $exactUnitId));
            }, function ($query) use ($scope) {
                $query->when($scope !== null, fn ($q) => $q->whereHas('user', fn ($userQuery) => $userQuery->whereIn('unit_id', $scope)));
            })
            ->with('user:id,unit_id', 'user.unit:id,unit_name')
            ->orderBy('full_name')
            ->get(['id', 'user_id', 'full_name', 'registration_number'])
            ->map(fn (Security $security) => [
                'value' => $security->id,
                'label' => $security->full_name.($security->registration_number ? ' ('.$security->registration_number.')' : ''),
                'unit_id' => $security->user?->unit_id,
                'unit_name' => $security->user?->unit?->unit_name ?? '-',
                'full_name' => $security->full_name,
                'registration_number' => $security->registration_number,
                'active_team_id' => $security->activeSecurityTeamMembership?->security_team_id,
            ])
            ->toArray();
    }

    /**
     * @param  array<int, int>|null  $scope
     * @return array<int, array<string, mixed>>
     */
    private function previousPeriods(?array $scope, ?int $excludeId = null): array
    {
        return SchedulePeriod::query()
            ->when($scope !== null, fn ($query) => $query->whereIn('unit_id', $scope))
            ->when($excludeId !== null, fn ($query) => $query->whereKeyNot($excludeId))
            ->withCount('members')
            ->with([
                'members.security:id,user_id,full_name,registration_number',
                'members.schedulePattern:id,name,cycle_length,is_active',
            ])
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (SchedulePeriod $period) => [
                'id' => $period->id,
                'name' => $period->name,
                'unit_id' => $period->unit_id,
                'security_post_id' => $period->security_post_id,
                'security_team_id' => $period->security_team_id,
                'start_date' => $period->start_date?->format('Y-m-d'),
                'end_date' => $period->end_date?->format('Y-m-d'),
                'status' => $period->status,
                'status_label' => $this->statusLabel($period->status),
                'members_count' => (int) $period->members_count,
                'members' => $period->members->map(fn ($member) => [
                    'security_id' => $member->security_id,
                    'full_name' => $member->security?->full_name ?? '-',
                    'registration_number' => $member->security?->registration_number,
                    'schedule_pattern_id' => $member->schedule_pattern_id,
                    'pattern_name' => $member->schedulePattern?->name,
                    'pattern_cycle_length' => $member->schedulePattern?->cycle_length,
                    'start_offset' => (int) $member->start_offset,
                ])->values(),
            ])
            ->toArray();
    }

    /**
     * Unit scope for the current user.
     *
     * - null  => unlimited (superadmin).
     * - array => allowed unit ids (user unit + descendants), [] when none.
     */
    private function resolveSelectedUnitId(Request $request, ?array $scope): ?int
    {
        if ($scope !== null) {
            $userUnitId = $request->user()?->unit_id;

            if ($userUnitId !== null && in_array((int) $userUnitId, $scope, true)) {
                return (int) $userUnitId;
            }

            return null;
        }

        $raw = trim((string) $request->query('unit_id', ''));

        if ($raw === '' || ! is_numeric($raw)) {
            return null;
        }

        $candidate = (int) $raw;

        return Unit::query()->whereKey($candidate)->exists() ? $candidate : null;
    }

    private function resolveSelectedPostId(Request $request, array $posts): ?int
    {
        $raw = trim((string) $request->query('security_post_id', ''));

        if ($raw === '' || ! is_numeric($raw)) {
            return null;
        }

        $candidate = (int) $raw;

        foreach ($posts as $post) {
            if ((int) $post['value'] === $candidate) {
                return $candidate;
            }
        }

        return null;
    }

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
     * @param  array<int, int>|null  $scope
     * @return array<int, string>
     */
    private function unitIdRules(?array $scope): array
    {
        return $scope === null
            ? ['integer', 'exists:units,id']
            : ['integer', Rule::in($scope)];
    }

    private function authorizeScope(Request $request, SchedulePeriod $period): void
    {
        $scope = $this->unitScope($request);

        if ($scope !== null && ! in_array((int) $period->unit_id, $scope, true)) {
            abort(403);
        }
    }

    private function queryInt(Request $request, string $key): ?int
    {
        $raw = trim((string) $request->query($key, ''));

        return $raw !== '' && is_numeric($raw) ? (int) $raw : null;
    }

    private function queryDate(Request $request, string $key): ?string
    {
        $raw = trim((string) $request->query($key, ''));

        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
