<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchedulePattern;
use App\Models\Shift;
use App\Models\Unit;
use App\Services\ActivityLogService;
use App\Support\AttendanceClock;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SchedulePatternController extends Controller
{
    /**
     * Rotation pattern list.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $sortColumns = [
            'name' => 'schedule_patterns.name',
            'cycle_length' => 'schedule_patterns.cycle_length',
            'status' => 'schedule_patterns.is_active',
            'dibuat' => 'schedule_patterns.created_at',
        ];

        $sort = (string) $request->query('sort', 'name');
        if (! array_key_exists($sort, $sortColumns) && $sort !== 'unit') {
            $sort = 'name';
        }
        $dir = $request->query('dir', 'asc') === 'desc' ? 'desc' : 'asc';

        $activeFilter = (string) $request->query('is_active', '');
        if (! in_array($activeFilter, ['0', '1'], true)) {
            $activeFilter = '';
        }

        $query = SchedulePattern::query()
            ->with(['unit', 'details'])
            ->withCount('details')
            ->when($search !== '', function ($query) use ($search) {
                $query->where('schedule_patterns.name', 'like', "%{$search}%");
            })
            ->when($activeFilter !== '', function ($query) use ($activeFilter) {
                $query->where('schedule_patterns.is_active', $activeFilter === '1');
            });

        $scope = $this->unitScope($request);
        if ($scope !== null) {
            $query->whereIn('schedule_patterns.unit_id', $scope);
        }

        $filterUnitId = $this->resolveFilterUnitId($request, $scope);
        if ($filterUnitId !== null) {
            $query->where('schedule_patterns.unit_id', $filterUnitId);
        }

        if ($sort === 'unit') {
            $query->leftJoin('units', 'schedule_patterns.unit_id', '=', 'units.id')
                ->orderBy('units.unit_name', $dir)
                ->select('schedule_patterns.*');
        } else {
            $query->orderBy($sortColumns[$sort], $dir);
        }

        $paginator = $query->paginate(50);

        return Inertia::render('Admin/SchedulePattern/Index', [
            'patterns' => $paginator->getCollection()->map(fn (SchedulePattern $pattern) => $this->listItem($pattern)),
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
            'unit_id' => $filterUnitId ?? '',
        ]);
    }

    /**
     * Rotation pattern create form.
     */
    public function create(Request $request): Response
    {
        $scope = $this->unitScope($request);

        if ($scope !== null && $request->user()?->unit_id === null) {
            abort(403);
        }

        return Inertia::render('Admin/SchedulePattern/Create', [
            'units' => $this->units($scope),
            'shifts' => $this->shiftOptions($scope),
        ]);
    }

    /**
     * Store a newly created rotation pattern with all cycle details.
     */
    public function store(Request $request): RedirectResponse
    {
        $scope = $this->unitScope($request);

        if ($scope !== null && $request->user()?->unit_id === null) {
            abort(403);
        }

        $this->normalizeInput($request);

        $validated = $request->validate([
            'unit_id' => array_merge($this->unitIdRules($request), ['required']),
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('schedule_patterns', 'name')->where(
                    fn ($query) => $query->where('unit_id', (int) $request->input('unit_id'))
                ),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'cycle_length' => ['required', 'integer', 'min:1', 'max:366'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->has('is_active')
            ? $request->boolean('is_active')
            : true;

        $unitId = (int) $validated['unit_id'];
        $cycleLength = (int) $validated['cycle_length'];
        $details = $this->validateDetails($request, $unitId, $cycleLength);

        $pattern = DB::transaction(function () use ($validated, $details) {
            $pattern = SchedulePattern::create($validated);

            foreach ($details as $detail) {
                $pattern->details()->create($detail);
            }

            return $pattern;
        });

        app(ActivityLogService::class)->log([
            'action' => 'created',
            'module' => 'Pola Rotasi',
            'description' => 'Membuat pola rotasi: '.$pattern->name,
            'subject' => $pattern,
            'unit_id' => $pattern->unit_id,
            'new_values' => ['name' => $pattern->name, 'cycle_length' => $pattern->cycle_length, 'is_active' => $pattern->is_active, 'details_count' => count($details)],
        ]);

        return redirect()
            ->route('admin.schedule-pattern.show', $pattern->id)
            ->with('success', 'Pola rotasi berhasil ditambahkan.');
    }

    /**
     * Rotation pattern detail.
     */
    public function show(Request $request, int $id): Response
    {
        $pattern = SchedulePattern::query()
            ->with(['unit', 'details.shift'])
            ->withCount(['schedulePeriodMembers', 'schedules'])
            ->findOrFail($id);

        $this->authorizeScope($request, $pattern);

        return Inertia::render('Admin/SchedulePattern/Show', [
            'pattern' => $this->transform($pattern),
        ]);
    }

    /**
     * Rotation pattern edit form.
     */
    public function edit(Request $request, int $id): Response
    {
        $pattern = SchedulePattern::query()
            ->with(['unit', 'details.shift'])
            ->withCount(['schedulePeriodMembers', 'schedules'])
            ->findOrFail($id);

        $this->authorizeScope($request, $pattern);

        $scope = $this->unitScope($request);

        return Inertia::render('Admin/SchedulePattern/Edit', [
            'pattern' => $this->transform($pattern),
            'units' => $this->units($scope),
            'shifts' => $this->shiftOptions($scope),
        ]);
    }

    /**
     * Update the specified rotation pattern and its cycle details.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $pattern = SchedulePattern::findOrFail($id);
        $this->authorizeScope($request, $pattern);

        $this->normalizeInput($request);

        $validated = $request->validate([
            'unit_id' => array_merge($this->unitIdRules($request), ['required']),
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('schedule_patterns', 'name')
                    ->where(fn ($query) => $query->where('unit_id', (int) $request->input('unit_id')))
                    ->ignore($pattern->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'cycle_length' => ['required', 'integer', 'min:1', 'max:366'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->has('is_active')
            ? $request->boolean('is_active')
            : $pattern->is_active;

        $isUsed = $this->isReferenced($pattern);

        if ($isUsed && (int) $validated['cycle_length'] !== (int) $pattern->cycle_length) {
            throw ValidationException::withMessages([
                'cycle_length' => 'Jumlah hari siklus tidak dapat diubah karena pola rotasi sudah digunakan pada periode atau jadwal.',
            ]);
        }

        if ($isUsed && (int) $validated['unit_id'] !== (int) $pattern->unit_id) {
            throw ValidationException::withMessages([
                'unit_id' => 'Unit pola rotasi tidak dapat diubah karena pola sudah digunakan pada periode atau jadwal.',
            ]);
        }

        $unitId = (int) $validated['unit_id'];
        $cycleLength = (int) $validated['cycle_length'];
        $details = $this->validateDetails($request, $unitId, $cycleLength);

        $old = $pattern->only(['unit_id', 'name', 'description', 'cycle_length', 'is_active']);
        DB::transaction(function () use ($pattern, $validated, $details) {
            $pattern->update($validated);
            $pattern->details()->delete();

            foreach ($details as $detail) {
                $pattern->details()->create($detail);
            }
        });

        app(ActivityLogService::class)->log([
            'action' => 'updated',
            'module' => 'Pola Rotasi',
            'description' => 'Mengubah pola rotasi: '.$pattern->name,
            'subject' => $pattern,
            'unit_id' => $pattern->unit_id,
            'old_values' => $old,
            'new_values' => array_merge($pattern->only(['unit_id', 'name', 'description', 'cycle_length', 'is_active']), ['details_count' => count($details)]),
        ]);

        return redirect()
            ->route('admin.schedule-pattern.show', $pattern->id)
            ->with('success', 'Pola rotasi berhasil diperbarui.');
    }

    /**
     * Toggle the rotation pattern active status.
     */
    public function toggle(Request $request, int $id): RedirectResponse
    {
        $pattern = SchedulePattern::findOrFail($id);
        $this->authorizeScope($request, $pattern);

        $pattern->update(['is_active' => ! $pattern->is_active]);

        return back()->with(
            'success',
            $pattern->is_active ? 'Pola rotasi berhasil diaktifkan.' : 'Pola rotasi berhasil dinonaktifkan.'
        );
    }

    /**
     * Remove the specified rotation pattern when it is not referenced.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $pattern = SchedulePattern::findOrFail($id);
        $this->authorizeScope($request, $pattern);

        if ($this->isReferenced($pattern)) {
            return back()->with(
                'error',
                'Pola rotasi tidak dapat dihapus karena sudah digunakan oleh periode atau jadwal. Nonaktifkan pola jika tidak ingin dipakai lagi.'
            );
        }

        $old = $pattern->only(['id', 'unit_id', 'name', 'cycle_length', 'is_active']);
        try {
            DB::transaction(fn () => $pattern->delete());
        } catch (QueryException) {
            return back()->with('error', 'Tidak dapat menghapus pola rotasi yang masih digunakan.');
        }

        app(ActivityLogService::class)->log([
            'action' => 'deleted',
            'module' => 'Pola Rotasi',
            'description' => 'Menghapus pola rotasi: '.($old['name'] ?? '-'),
            'subject_type' => SchedulePattern::class,
            'subject_id' => $old['id'] ?? $id,
            'unit_id' => $old['unit_id'] ?? null,
            'old_values' => $old,
        ]);

        return redirect()
            ->route('admin.schedule-pattern.index')
            ->with('success', 'Pola rotasi berhasil dihapus.');
    }

    /**
     * Normalize user input before validation.
     */
    private function normalizeInput(Request $request): void
    {
        $description = trim((string) $request->input('description', ''));

        $request->merge([
            'name' => trim((string) $request->input('name')),
            'description' => $description === '' ? null : $description,
        ]);
    }

    /**
     * Validate the complete ordered cycle details.
     *
     * @return array<int, array{day_order: int, shift_id: int}>
     */
    private function validateDetails(Request $request, int $unitId, int $cycleLength): array
    {
        $details = $request->input('details');

        if (! is_array($details) || count($details) !== $cycleLength) {
            throw ValidationException::withMessages([
                'details' => "Jumlah detail pola harus sama dengan {$cycleLength} hari siklus.",
            ]);
        }

        $rules = [];
        $messages = [];

        for ($day = 1; $day <= $cycleLength; $day++) {
            $index = $day - 1;

            $rules["details.{$index}.day_order"] = ['required', 'integer', 'in:'.$day];
            $rules["details.{$index}.shift_id"] = [
                'required',
                'integer',
                Rule::exists('shifts', 'id')->where(
                    fn ($query) => $query->where('unit_id', $unitId)->where('is_active', true)
                ),
            ];

            $messages["details.{$index}.day_order.required"] = "Urutan hari ke-{$day} wajib diisi.";
            $messages["details.{$index}.day_order.in"] = "Urutan hari ke-{$day} tidak valid atau duplikat.";
            $messages["details.{$index}.shift_id.required"] = "Shift untuk hari ke-{$day} wajib dipilih.";
            $messages["details.{$index}.shift_id.exists"] = "Shift untuk hari ke-{$day} harus shift aktif dari unit yang sama.";
        }

        $validated = $request->validate($rules, $messages);

        $result = [];

        for ($day = 1; $day <= $cycleLength; $day++) {
            $index = $day - 1;

            $result[] = [
                'day_order' => $day,
                'shift_id' => (int) $validated['details'][$index]['shift_id'],
            ];
        }

        return $result;
    }

    private function isReferenced(SchedulePattern $pattern): bool
    {
        return $pattern->schedulePeriodMembers()->exists() || $pattern->schedules()->exists();
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
    private function authorizeScope(Request $request, SchedulePattern $pattern): void
    {
        $scope = $this->unitScope($request);

        if ($scope !== null && ! in_array((int) $pattern->unit_id, $scope, true)) {
            abort(403);
        }
    }

    private function listItem(SchedulePattern $pattern): array
    {
        return [
            'id' => $pattern->id,
            'name' => $pattern->name,
            'description' => $pattern->description,
            'unit_id' => $pattern->unit_id,
            'unit_name' => $pattern->unit?->unit_name ?? '-',
            'cycle_length' => (int) $pattern->cycle_length,
            'details_count' => (int) $pattern->details_count,
            'shifts_count' => $pattern->details->pluck('shift_id')->unique()->count(),
            'is_active' => $pattern->is_active,
            'status' => $pattern->is_active ? 'Aktif' : 'Nonaktif',
        ];
    }

    private function transform(SchedulePattern $pattern): array
    {
        return [
            'id' => $pattern->id,
            'unit_id' => $pattern->unit_id,
            'unit_name' => $pattern->unit?->unit_name ?? '-',
            'name' => $pattern->name,
            'description' => $pattern->description,
            'cycle_length' => (int) $pattern->cycle_length,
            'is_active' => $pattern->is_active,
            'status' => $pattern->is_active ? 'Aktif' : 'Nonaktif',
            'is_used' => $pattern->schedule_period_members_count > 0 || $pattern->schedules_count > 0,
            'period_members_count' => (int) $pattern->schedule_period_members_count,
            'schedules_count' => (int) $pattern->schedules_count,
            'details' => $pattern->details->map(fn ($detail) => [
                'day_order' => (int) $detail->day_order,
                'shift_id' => $detail->shift_id,
                'shift_code' => $detail->shift?->code,
                'shift_name' => $detail->shift?->name,
                'shift_time' => $this->timeRange(
                    $detail->shift?->start_time,
                    $detail->shift?->end_time,
                    (int) ($detail->shift?->end_day_offset ?? 0)
                ),
                'shift_is_active' => (bool) $detail->shift?->is_active,
            ])->values(),
            'created_at' => AttendanceClock::formatWib($pattern->created_at),
            'updated_at' => AttendanceClock::formatWib($pattern->updated_at),
        ];
    }

    private function timeRange(?string $start, ?string $end, int $endDayOffset): ?string
    {
        if ($start === null || $start === '' || $end === null || $end === '') {
            return null;
        }

        return substr($start, 0, 5).' - '.substr($end, 0, 5).($endDayOffset ? ' (+1 hari)' : '');
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
     * @return array<int, array<string, mixed>>
     */
    private function shiftOptions(?array $scope): array
    {
        return Shift::query()
            ->when($scope !== null, fn ($query) => $query->whereIn('unit_id', $scope))
            ->where('is_active', true)
            ->orderBy('unit_id')
            ->orderBy('code')
            ->get(['id', 'unit_id', 'name', 'code', 'start_time', 'end_time', 'end_day_offset'])
            ->map(fn (Shift $shift) => [
                'id' => $shift->id,
                'unit_id' => $shift->unit_id,
                'name' => $shift->name,
                'code' => $shift->code,
                'label' => $shift->code.' — '.$shift->name,
                'time_label' => $this->timeRange(
                    $shift->start_time,
                    $shift->end_time,
                    (int) $shift->end_day_offset
                ),
            ])
            ->toArray();
    }
}
