<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\Unit;
use App\Support\AttendanceClock;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ShiftController extends Controller
{
    /**
     * Shift list.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $sortColumns = [
            'code' => 'shifts.code',
            'name' => 'shifts.name',
            'start_time' => 'shifts.start_time',
            'end_time' => 'shifts.end_time',
            'status' => 'shifts.is_active',
            'dibuat' => 'shifts.created_at',
        ];

        $sort = (string) $request->query('sort', 'name');
        if (! array_key_exists($sort, $sortColumns) && $sort !== 'unit') {
            $sort = 'name';
        }
        $dir = $request->query('dir', 'asc') === 'desc' ? 'desc' : 'asc';

        $query = Shift::query()
            ->with('unit')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('shifts.code', 'like', "%{$search}%")
                        ->orWhere('shifts.name', 'like', "%{$search}%");
                });
            });

        $scope = $this->unitScope($request);
        if ($scope !== null) {
            $query->whereIn('shifts.unit_id', $scope);
        }

        $filterUnitId = $this->resolveFilterUnitId($request, $scope);
        if ($filterUnitId !== null) {
            $query->where('shifts.unit_id', $filterUnitId);
        }

        if ($sort === 'unit') {
            $query->leftJoin('units', 'shifts.unit_id', '=', 'units.id')
                ->orderBy('units.unit_name', $dir)
                ->select('shifts.*');
        } else {
            $query->orderBy($sortColumns[$sort], $dir);
        }

        $paginator = $query->paginate(50);

        return Inertia::render('Admin/Shift/Index', [
            'shifts' => $paginator->getCollection()->map(fn (Shift $shift) => $this->listItem($shift)),
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
     * Shift create form.
     */
    public function create(Request $request): Response
    {
        $scope = $this->unitScope($request);

        if ($scope !== null && $request->user()?->unit_id === null) {
            abort(403);
        }

        return Inertia::render('Admin/Shift/Create', [
            'units' => $this->units($scope),
        ]);
    }

    /**
     * Store a newly created shift.
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
                Rule::in(Shift::NAMES),
                Rule::unique('shifts', 'name')->where(
                    fn ($query) => $query->where('unit_id', (int) $request->input('unit_id'))
                ),
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('shifts', 'code')->where(
                    fn ($query) => $query->where('unit_id', (int) $request->input('unit_id'))
                ),
            ],
            'type' => ['nullable', 'string', Rule::in([Shift::TYPE_WORKING, Shift::TYPE_DAY_OFF])],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'end_day_offset' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['name'] = trim((string) $validated['name']);
        $validated['code'] = Shift::CODE_BY_NAME[$validated['name']] ?? $validated['code'];
        $validated['type'] = Shift::TYPE_BY_NAME[$validated['name']] ?? Shift::TYPE_WORKING;
        $validated['end_day_offset'] = $request->boolean('end_day_offset');
        $validated['is_active'] = $request->has('is_active')
            ? $request->boolean('is_active')
            : true;

        $this->validateTimeRange($validated);

        if ($validated['type'] === Shift::TYPE_DAY_OFF) {
            $validated['start_time'] = null;
            $validated['end_time'] = null;
            $validated['end_day_offset'] = false;
        }

        Shift::create($validated);

        return redirect()
            ->route('admin.shift.index')
            ->with('success', 'Shift berhasil ditambahkan.');
    }

    /**
     * Shift detail.
     */
    public function show(Request $request, int $id): Response
    {
        $shift = Shift::with('unit')->findOrFail($id);
        $this->authorizeScope($request, $shift);

        return Inertia::render('Admin/Shift/Show', [
            'shift' => $this->transform($shift),
        ]);
    }

    /**
     * Shift edit form.
     */
    public function edit(Request $request, int $id): Response
    {
        $shift = Shift::with('unit')->findOrFail($id);
        $this->authorizeScope($request, $shift);

        $scope = $this->unitScope($request);

        return Inertia::render('Admin/Shift/Edit', [
            'shift' => $this->transform($shift),
            'units' => $this->units($scope),
        ]);
    }

    /**
     * Update the specified shift.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $shift = Shift::findOrFail($id);
        $this->authorizeScope($request, $shift);

        $this->normalizeInput($request);

        $validated = $request->validate([
            'unit_id' => array_merge($this->unitIdRules($request), ['required']),
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::in(Shift::NAMES),
                Rule::unique('shifts', 'name')
                    ->where(fn ($query) => $query->where('unit_id', (int) $request->input('unit_id')))
                    ->ignore($shift->id),
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('shifts', 'code')
                    ->where(fn ($query) => $query->where('unit_id', (int) $request->input('unit_id')))
                    ->ignore($shift->id),
            ],
            'type' => ['nullable', 'string', Rule::in([Shift::TYPE_WORKING, Shift::TYPE_DAY_OFF])],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'end_day_offset' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['name'] = trim((string) $validated['name']);
        $validated['code'] = Shift::CODE_BY_NAME[$validated['name']] ?? $validated['code'];
        $validated['type'] = Shift::TYPE_BY_NAME[$validated['name']] ?? ($shift->type ?? Shift::TYPE_WORKING);
        $validated['end_day_offset'] = $request->boolean('end_day_offset');
        $validated['is_active'] = $request->has('is_active')
            ? $request->boolean('is_active')
            : $shift->is_active;

        $this->validateTimeRange($validated);

        if ($validated['type'] === Shift::TYPE_DAY_OFF) {
            $validated['start_time'] = null;
            $validated['end_time'] = null;
            $validated['end_day_offset'] = false;
        }

        if ((int) $validated['unit_id'] !== (int) $shift->unit_id && $this->isReferenced($shift)) {
            throw ValidationException::withMessages([
                'unit_id' => 'Unit shift tidak dapat diubah karena shift sudah digunakan oleh pola rotasi atau jadwal.',
            ]);
        }

        $shift->update($validated);

        return redirect()
            ->route('admin.shift.show', $shift->id)
            ->with('success', 'Shift berhasil diperbarui.');
    }

    /**
     * Toggle the shift active status.
     */
    public function toggle(Request $request, int $id): RedirectResponse
    {
        $shift = Shift::findOrFail($id);
        $this->authorizeScope($request, $shift);

        $shift->update(['is_active' => ! $shift->is_active]);

        return back()->with(
            'success',
            $shift->is_active ? 'Shift berhasil diaktifkan.' : 'Shift berhasil dinonaktifkan.'
        );
    }

    /**
     * Remove the specified shift.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $shift = Shift::findOrFail($id);
        $this->authorizeScope($request, $shift);

        if ($this->isReferenced($shift)) {
            return back()->with(
                'error',
                'Shift tidak dapat dihapus karena sudah digunakan oleh pola rotasi atau jadwal. Nonaktifkan shift jika tidak ingin dipakai lagi.'
            );
        }

        try {
            DB::transaction(fn () => $shift->delete());
        } catch (QueryException) {
            return back()->with('error', 'Tidak dapat menghapus shift yang masih digunakan.');
        }

        return redirect()
            ->route('admin.shift.index')
            ->with('success', 'Shift berhasil dihapus.');
    }

    /**
     * Normalize user input before validation.
     */
    private function normalizeInput(Request $request): void
    {
        $name = trim((string) $request->input('name'));

        $request->merge([
            'name' => $name,
            'code' => Shift::CODE_BY_NAME[$name] ?? '',
        ]);
    }

    /**
     * Validate the time pair and cross-midnight flag.
     *
     * @param  array<string, mixed>  $validated
     */
    private function validateTimeRange(array $validated): void
    {
        $type = $validated['type'] ?? Shift::TYPE_WORKING;
        $start = $validated['start_time'] ?? null;
        $end = $validated['end_time'] ?? null;
        $crossesMidnight = (bool) ($validated['end_day_offset'] ?? false);

        if ($type === Shift::TYPE_DAY_OFF) {
            if ($start !== null || $end !== null || $crossesMidnight) {
                throw ValidationException::withMessages([
                    'start_time' => 'Shift Libur tidak boleh memiliki jam kerja.',
                ]);
            }

            return;
        }

        if ($start === null || $end === null) {
            throw ValidationException::withMessages([
                'start_time' => 'Jam mulai dan jam selesai wajib diisi untuk Shift Kerja.',
            ]);
        }

        if (($start === null) !== ($end === null)) {
            throw ValidationException::withMessages([
                'start_time' => 'Jam mulai dan jam selesai harus diisi bersamaan.',
            ]);
        }

        if ($crossesMidnight && $end > $start) {
            throw ValidationException::withMessages([
                'end_time' => 'Untuk shift lintas tengah malam, jam selesai tidak boleh lebih besar dari jam mulai.',
            ]);
        }

        if (! $crossesMidnight && $end <= $start) {
            throw ValidationException::withMessages([
                'end_time' => 'Jam selesai harus lebih akhir dari jam mulai. Aktifkan opsi lintas tengah malam jika shift melewati hari.',
            ]);
        }
    }

    private function isReferenced(Shift $shift): bool
    {
        return $shift->patternDetails()->exists() || $shift->schedules()->exists();
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
    private function authorizeScope(Request $request, Shift $shift): void
    {
        $scope = $this->unitScope($request);

        if ($scope !== null && ! in_array((int) $shift->unit_id, $scope, true)) {
            abort(403);
        }
    }

    private function listItem(Shift $shift): array
    {
        return [
            'id' => $shift->id,
            'code' => $shift->code,
            'name' => $shift->name,
            'unit_id' => $shift->unit_id,
            'unit_name' => $shift->unit?->unit_name ?? '-',
            'type' => $shift->type ?? Shift::TYPE_WORKING,
            'type_label' => ($shift->type ?? Shift::TYPE_WORKING) === Shift::TYPE_DAY_OFF ? 'Libur' : 'Kerja',
            'start_time' => $this->timeLabel($shift->start_time),
            'end_time' => $this->timeLabel($shift->end_time),
            'end_day_offset' => (int) $shift->end_day_offset,
            'is_active' => $shift->is_active,
            'status' => $shift->is_active ? 'Aktif' : 'Nonaktif',
        ];
    }

    private function transform(Shift $shift): array
    {
        return [
            'id' => $shift->id,
            'unit_id' => $shift->unit_id,
            'unit_name' => $shift->unit?->unit_name ?? '-',
            'name' => $shift->name,
            'code' => $shift->code,
            'type' => $shift->type ?? Shift::TYPE_WORKING,
            'type_label' => ($shift->type ?? Shift::TYPE_WORKING) === Shift::TYPE_DAY_OFF ? 'Libur' : 'Kerja',
            'start_time' => $this->timeLabel($shift->start_time),
            'end_time' => $this->timeLabel($shift->end_time),
            'end_day_offset' => (int) $shift->end_day_offset,
            'is_active' => $shift->is_active,
            'status' => $shift->is_active ? 'Aktif' : 'Nonaktif',
            'created_at' => AttendanceClock::formatWib($shift->created_at),
            'updated_at' => AttendanceClock::formatWib($shift->updated_at),
        ];
    }

    private function timeLabel(?string $time): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }

        return substr($time, 0, 5);
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
