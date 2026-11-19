<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\SchedulePeriod;
use App\Models\Security;
use App\Models\SecurityPost;
use App\Models\SecurityTeam;
use App\Models\Shift;
use App\Models\Unit;
use App\Support\AttendanceClock;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleCalendarController extends Controller
{
    /**
     * Monthly calendar and monitoring view for stored schedules.
     */
    public function index(Request $request): Response
    {
        $scope = $this->unitScope($request);
        $isSuperadmin = $request->user()?->role === 'superadmin';

        $month = $this->queryMonth($request);
        $monthStart = Carbon::createFromFormat(
            'Y-m',
            $month,
            AttendanceClock::OPERATIONAL_TIMEZONE
        )->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $status = (string) $request->query('status', '');
        if (! in_array($status, $this->statuses(), true)) {
            $status = '';
        }

        $view = $request->query('view') === 'list' ? 'list' : 'calendar';

        $requiresUnitSelection = false;

        if ($isSuperadmin) {
            $rawUnitId = trim((string) $request->query('unit_id', ''));

            if ($rawUnitId === '' || ! is_numeric($rawUnitId)) {
                $requiresUnitSelection = true;
                $filterUnitId = null;
            } else {
                $candidate = (int) $rawUnitId;
                $exists = Unit::query()->whereKey($candidate)->exists();

                if (! $exists) {
                    $requiresUnitSelection = true;
                    $filterUnitId = null;
                } else {
                    $filterUnitId = $candidate;
                }
            }
        } else {
            $filterUnitId = $this->resolveFilterUnitId($request, $scope);
        }

        $filterPostId = $this->resolvePostId($request, $scope);
        $filterTeamId = $this->resolveTeamId($request, $scope, $filterPostId);
        $filterSecurityId = $this->resolveSecurityId($request, $scope);

        if ($requiresUnitSelection) {
            return Inertia::render('Admin/ScheduleCalendar/Index', [
                'month' => $month,
                'month_label' => $this->monthLabel($monthStart),
                'prev_month' => $monthStart->copy()->subMonthNoOverflow()->format('Y-m'),
                'next_month' => $monthStart->copy()->addMonthNoOverflow()->format('Y-m'),
                'days' => $this->days($monthStart, $monthEnd),
                'leading_blanks' => $monthStart->dayOfWeekIso - 1,
                'rows' => [],
                'legend' => [],
                'units' => $this->units($scope),
                'posts' => [],
                'teams' => [],
                'securities' => [],
                'filters' => [
                    'month' => $month,
                    'unit_id' => '',
                    'security_post_id' => '',
                    'security_team_id' => '',
                    'security_id' => '',
                    'status' => $status,
                    'view' => $view,
                ],
                'statusOptions' => [
                    ['value' => '', 'label' => 'Semua Status'],
                    ['value' => SchedulePeriod::STATUS_DRAFT, 'label' => 'Draft'],
                    ['value' => SchedulePeriod::STATUS_ACTIVE, 'label' => 'Aktif'],
                    ['value' => SchedulePeriod::STATUS_COMPLETED, 'label' => 'Selesai'],
                ],
                'requires_unit_selection' => true,
            ]);
        }

        $query = Schedule::query()
            ->with([
                'security:id,user_id,full_name,registration_number',
                'shift:id,unit_id,name,code,type,start_time,end_time,end_day_offset,color,is_active',
                'schedulePeriod:id,unit_id,security_post_id,security_team_id,name,status',
                'schedulePeriod.unit:id,unit_name',
                'schedulePeriod.securityPost:id,unit_id,post_code,name',
                'schedulePeriod.securityTeam:id,security_post_id,code,name',
                'updater:id,name',
            ])
            ->whereDate('schedules.date', '>=', $monthStart->toDateString())
            ->whereDate('schedules.date', '<=', $monthEnd->toDateString());

        $query->whereHas('schedulePeriod', function ($periodQuery) use ($scope, $filterUnitId, $filterPostId, $filterTeamId, $status) {
            if ($scope !== null) {
                $periodQuery->whereIn('unit_id', $scope);
            }

            if ($filterUnitId !== null) {
                $periodQuery->where('unit_id', $filterUnitId);
            }

            if ($filterPostId !== null) {
                $periodQuery->where('security_post_id', $filterPostId);
            }

            if ($filterTeamId !== null) {
                $periodQuery->where('security_team_id', $filterTeamId);
            }

            if ($status !== '') {
                $periodQuery->where('status', $status);
            }
        });

        if ($filterSecurityId !== null) {
            $query->where('schedules.security_id', $filterSecurityId);
        }

        $schedules = $query
            ->orderBy('schedules.date')
            ->orderBy('schedules.security_id')
            ->get();

        $rows = $schedules
            ->map(fn (Schedule $schedule) => $this->row($schedule))
            ->values()
            ->all();

        return Inertia::render('Admin/ScheduleCalendar/Index', [
            'month' => $month,
            'month_label' => $this->monthLabel($monthStart),
            'prev_month' => $monthStart->copy()->subMonthNoOverflow()->format('Y-m'),
            'next_month' => $monthStart->copy()->addMonthNoOverflow()->format('Y-m'),
            'days' => $this->days($monthStart, $monthEnd),
            'leading_blanks' => $monthStart->dayOfWeekIso - 1,
            'rows' => $rows,
            'legend' => $this->legend($schedules),
            'units' => $this->units($scope),
            'posts' => $this->posts($scope, $filterUnitId),
            'teams' => $this->teams($scope, $filterPostId, $filterUnitId),
            'securities' => $this->securities($scope, $filterUnitId, $filterPostId, $filterTeamId),
            'filters' => [
                'month' => $month,
                'unit_id' => $filterUnitId ?? '',
                'security_post_id' => $filterPostId ?? '',
                'security_team_id' => $filterTeamId ?? '',
                'security_id' => $filterSecurityId ?? '',
                'status' => $status,
                'view' => $view,
            ],
            'statusOptions' => [
                ['value' => '', 'label' => 'Semua Status'],
                ['value' => SchedulePeriod::STATUS_DRAFT, 'label' => 'Draft'],
                ['value' => SchedulePeriod::STATUS_ACTIVE, 'label' => 'Aktif'],
                ['value' => SchedulePeriod::STATUS_COMPLETED, 'label' => 'Selesai'],
            ],
            'requires_unit_selection' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Schedule $schedule): array
    {
        $period = $schedule->schedulePeriod;
        $shift = $schedule->shift;

        return [
            'id' => $schedule->id,
            'date' => $schedule->date->toDateString(),
            'date_label' => $schedule->date->format('d M Y'),
            'day' => $schedule->date->day,
            'security_id' => $schedule->security_id,
            'full_name' => $schedule->security?->full_name ?? '-',
            'registration_number' => $schedule->security?->registration_number,
            'shift_id' => $schedule->shift_id,
            'shift_name' => $shift?->name,
            'shift_code' => $shift?->code,
            'shift_color' => $shift?->color,
            'shift_time' => $this->shiftTime($shift),
            'end_day_offset' => (int) ($shift?->end_day_offset ?? 0),
            'is_off' => $this->isOffShift($shift),
            'source' => $schedule->source,
            'notes' => $schedule->notes,
            'updated_by_name' => $schedule->updater?->name,
            'updated_at' => AttendanceClock::formatWib($schedule->updated_at),
            'period_id' => $schedule->schedule_period_id,
            'period_name' => $period?->name,
            'period_status' => $period?->status,
            'period_status_label' => $period ? $this->statusLabel($period->status) : null,
            'unit_id' => $period?->unit_id,
            'unit_name' => $period?->unit?->unit_name,
            'post_name' => $period?->securityPost?->name,
            'team_name' => $period?->securityTeam?->name,
            'can_edit' => $period !== null && $period->status !== SchedulePeriod::STATUS_COMPLETED,
        ];
    }

    /**
     * @param  Collection<int, Schedule>  $schedules
     * @return array<int, array<string, mixed>>
     */
    private function legend($schedules): array
    {
        return $schedules
            ->map(fn (Schedule $schedule) => $schedule->shift)
            ->filter()
            ->unique('id')
            ->map(fn (Shift $shift) => [
                'id' => $shift->id,
                'name' => $shift->name,
                'code' => $shift->code,
                'color' => $shift->color,
                'is_off' => $this->isOffShift($shift),
            ])
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function days(Carbon $start, Carbon $end): array
    {
        $weekdayLabels = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];
        $today = AttendanceClock::todayWib();

        $days = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $days[] = [
                'date' => $cursor->toDateString(),
                'day' => $cursor->day,
                'weekday' => $weekdayLabels[$cursor->dayOfWeekIso],
                'is_weekend' => in_array($cursor->dayOfWeekIso, [6, 7], true),
                'is_today' => $cursor->toDateString() === $today,
            ];

            $cursor->addDay();
        }

        return $days;
    }

    private function shiftTime(?Shift $shift): ?string
    {
        if ($shift === null || ! $shift->start_time || ! $shift->end_time) {
            return null;
        }

        return substr($shift->start_time, 0, 5).' - '.substr($shift->end_time, 0, 5)
            .($shift->end_day_offset ? ' (+1 hari)' : '');
    }

    private function isOffShift(?Shift $shift): bool
    {
        if ($shift === null) {
            return false;
        }

        if (method_exists($shift, 'isDayOff') && $shift->isDayOff()) {
            return true;
        }

        if (($shift->type ?? null) === Shift::TYPE_DAY_OFF) {
            return true;
        }

        $haystack = Str::lower(trim(($shift->name ?? '').' '.($shift->code ?? '')));

        return Str::contains($haystack, ['libur', 'off', 'lepas']);
    }

    private function monthLabel(Carbon $date): string
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return $months[$date->month].' '.$date->year;
    }

    private function queryMonth(Request $request): string
    {
        $raw = trim((string) $request->query('month', ''));

        if ($raw !== '') {
            try {
                return Carbon::createFromFormat(
                    'Y-m',
                    $raw,
                    AttendanceClock::OPERATIONAL_TIMEZONE
                )->format('Y-m');
            } catch (\Throwable) {
                // Fall through to the current operational month.
            }
        }

        return AttendanceClock::nowWib()->format('Y-m');
    }

    /**
     * @param  array<int, int>|null  $scope
     */
    private function resolvePostId(Request $request, ?array $scope): ?int
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

        return $postId;
    }

    /**
     * @param  array<int, int>|null  $scope
     */
    private function resolveTeamId(Request $request, ?array $scope, ?int $postId): ?int
    {
        $teamId = $this->queryInt($request, 'security_team_id');

        if ($teamId === null) {
            return null;
        }

        $team = SecurityTeam::query()->with('securityPost:id,unit_id')->find($teamId);

        if ($team === null || $team->securityPost === null) {
            return null;
        }

        if ($scope !== null && ! in_array((int) $team->securityPost->unit_id, $scope, true)) {
            return null;
        }

        if ($postId !== null && (int) $team->security_post_id !== $postId) {
            return null;
        }

        return $teamId;
    }

    /**
     * @param  array<int, int>|null  $scope
     */
    private function resolveSecurityId(Request $request, ?array $scope): ?int
    {
        $securityId = $this->queryInt($request, 'security_id');

        if ($securityId === null) {
            return null;
        }

        $security = Security::query()->with('user:id,unit_id')->find($securityId);

        if ($security === null) {
            return null;
        }

        if ($scope !== null && ! in_array((int) $security->user?->unit_id, $scope, true)) {
            return null;
        }

        return $securityId;
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
    private function posts(?array $scope, ?int $unitId): array
    {
        return SecurityPost::query()
            ->when($scope !== null, fn ($query) => $query->whereIn('unit_id', $scope))
            ->when($unitId !== null, fn ($query) => $query->where('unit_id', $unitId))
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
     * @return array<int, array{value: int, label: string, security_post_id: int, unit_id: int|null}>
     */
    private function teams(?array $scope, ?int $postId, ?int $unitId): array
    {
        return SecurityTeam::query()
            ->when($scope !== null, function ($query) use ($scope) {
                $query->whereHas('securityPost', fn ($postQuery) => $postQuery->whereIn('unit_id', $scope));
            })
            ->when($postId !== null, fn ($query) => $query->where('security_post_id', $postId))
            ->when($postId === null && $unitId !== null, function ($query) use ($unitId) {
                $query->whereHas('securityPost', fn ($postQuery) => $postQuery->where('unit_id', $unitId));
            })
            ->with('securityPost:id,unit_id')
            ->orderBy('name')
            ->get(['id', 'security_post_id', 'name', 'code'])
            ->map(fn (SecurityTeam $team) => [
                'value' => $team->id,
                'label' => $team->code.' — '.$team->name,
                'security_post_id' => $team->security_post_id,
                'unit_id' => $team->securityPost?->unit_id,
            ])
            ->toArray();
    }

    /**
     * @param  array<int, int>|null  $scope
     * @return array<int, array{value: int, label: string, unit_id: int|null}>
     */
    private function securities(?array $scope, ?int $unitId, ?int $postId, ?int $teamId): array
    {
        return Security::query()
            ->when($scope !== null, function ($query) use ($scope) {
                $query->whereHas('user', fn ($userQuery) => $userQuery->whereIn('unit_id', $scope));
            })
            ->when($unitId !== null, function ($query) use ($unitId) {
                $query->whereHas('user', fn ($userQuery) => $userQuery->where('unit_id', $unitId));
            })
            ->when($teamId !== null, function ($query) use ($teamId) {
                $query->whereHas('schedulePeriodMembers.schedulePeriod', function ($periodQuery) use ($teamId) {
                    $periodQuery->where('security_team_id', $teamId);
                });
            })
            ->when($postId !== null, function ($query) use ($postId) {
                $query->whereHas('schedulePeriodMembers.schedulePeriod', function ($periodQuery) use ($postId) {
                    $periodQuery->where('security_post_id', $postId);
                });
            })
            ->with('user:id,unit_id')
            ->orderBy('full_name')
            ->get(['id', 'user_id', 'full_name', 'registration_number'])
            ->map(fn (Security $security) => [
                'value' => $security->id,
                'label' => $security->full_name.($security->registration_number ? ' ('.$security->registration_number.')' : ''),
                'unit_id' => $security->user?->unit_id,
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

    /**
     * Effective unit filter for the calendar (scope-safe).
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

    private function queryInt(Request $request, string $key): ?int
    {
        $raw = trim((string) $request->query($key, ''));

        return $raw !== '' && is_numeric($raw) ? (int) $raw : null;
    }
}
