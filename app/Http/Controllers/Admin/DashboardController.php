<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Apar;
use App\Models\AparInspection;
use App\Models\AttendanceRecord;
use App\Models\GuestVisit;
use App\Models\Hydrant;
use App\Models\HydrantInspection;
use App\Models\PatrolScan;
use App\Models\PatrolSession;
use App\Models\Schedule;
use App\Models\Security;
use App\Models\SecurityPost;
use App\Models\SecurityTeam;
use App\Models\Unit;
use App\Support\AttendanceClock;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const PERIODS = ['today', '7d', '30d'];

    public function index(Request $request): Response
    {
        $scope = $this->unitScope($request);
        $period = $this->resolvePeriod($request);
        [$periodStart, $periodEnd, $periodLabel] = $this->periodRange($period);
        $today = AttendanceClock::todayWib();

        $securityPosts = $this->securityPosts($scope);

        $props = [
            'filters' => [
                'period' => $period,
                'period_label' => $periodLabel,
            ],
            'context' => [
                'today' => $this->formatDateId($today),
                'today_iso' => $today,
                'unit_scope_label' => $this->unitScopeLabel($request),
            ],
            'securityPosts' => $securityPosts,
            'personnel' => $this->personnelStats($scope),
            'patrol' => $this->patrolStats($scope, $today, $periodStart, $periodEnd),
            'attendance' => $this->attendanceStats($scope, $today),
            'schedule' => $this->scheduleStats($scope, $today),
            'assets' => $this->assetStats($scope),
            'guestBook' => $this->guestBookStats($scope, $today),
            'activities' => $this->recentActivities($scope, $periodStart, $periodEnd),
        ];

        return Inertia::render('Admin/Dashboard', $props);
    }

    private function resolvePeriod(Request $request): string
    {
        $period = (string) $request->query('period', 'today');

        return in_array($period, self::PERIODS, true) ? $period : 'today';
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private function periodRange(string $period): array
    {
        $today = AttendanceClock::todayWib();
        $end = $today;

        $start = match ($period) {
            '7d' => Carbon::parse($today, AttendanceClock::OPERATIONAL_TIMEZONE)->subDays(6)->toDateString(),
            '30d' => Carbon::parse($today, AttendanceClock::OPERATIONAL_TIMEZONE)->subDays(29)->toDateString(),
            default => $today,
        };

        $label = match ($period) {
            '7d' => '7 hari terakhir',
            '30d' => '30 hari terakhir',
            default => 'Hari ini',
        };

        return [$start, $end, $label];
    }

    private function unitScopeLabel(Request $request): string
    {
        $user = $request->user();

        if ($user === null) {
            return '-';
        }

        if ($user->role === 'superadmin') {
            return 'Seluruh Unit';
        }

        if ($user->unit_id !== null) {
            $name = $user->unit?->unit_name ?? Unit::query()->whereKey($user->unit_id)->value('unit_name');

            return $name ? 'Unit '.$name.' dan turunannya' : 'Unit sendiri dan turunannya';
        }

        return 'Tidak ada cakupan unit';
    }

    /**
     * @param  array<int,int>|null  $scope
     * @return array<int, array<string, mixed>>
     */
    private function securityPosts(?array $scope): array
    {
        return SecurityPost::query()
            ->with('unit:id,unit_name')
            ->when($scope !== null, fn ($q) => $q->whereIn('unit_id', $scope))
            ->orderBy('name')
            ->get(['id', 'name', 'address', 'latitude', 'longitude', 'unit_id'])
            ->map(fn (SecurityPost $post) => [
                'id' => $post->id,
                'name' => $post->name,
                'address' => $post->address,
                'latitude' => $post->latitude,
                'longitude' => $post->longitude,
                'unit_name' => $post->unit?->unit_name,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int,int>|null  $scope
     * @return array<string, int>
     */
    private function personnelStats(?array $scope): array
    {
        $securityQuery = Security::query()
            ->whereHas('user', function ($q) use ($scope) {
                $q->where('role', 'security');
                if ($scope !== null) {
                    $q->whereIn('unit_id', $scope);
                }
            });

        $teamsQuery = SecurityTeam::query()
            ->whereHas('securityPost', function ($q) use ($scope) {
                if ($scope !== null) {
                    $q->whereIn('unit_id', $scope);
                }
            });

        $postsQuery = SecurityPost::query()
            ->when($scope !== null, fn ($q) => $q->whereIn('unit_id', $scope));

        $unitsQuery = Unit::query()
            ->when($scope !== null, fn ($q) => $q->whereIn('id', $scope));

        return [
            'guards_total' => (clone $securityQuery)->count(),
            'guards_active' => (clone $securityQuery)->whereHas('user', fn ($q) => $q->where('is_active', true))->count(),
            'teams_total' => $teamsQuery->count(),
            'posts_total' => $postsQuery->count(),
            'units_total' => $unitsQuery->count(),
        ];
    }

    /**
     * @param  array<int,int>|null  $scope
     * @return array<string, mixed>
     */
    private function patrolStats(?array $scope, string $today, string $periodStart, string $periodEnd): array
    {
        $todayBase = PatrolSession::query()
            ->whereDate('work_date', $today)
            ->when($scope !== null, fn ($q) => $q->whereIn('unit_id', $scope));

        $statusCounts = [
            PatrolSession::STATUS_PENDING => 0,
            PatrolSession::STATUS_ACTIVE => 0,
            PatrolSession::STATUS_COMPLETED => 0,
            PatrolSession::STATUS_INCOMPLETE => 0,
            PatrolSession::STATUS_NOT_STARTED => 0,
        ];

        $grouped = (clone $todayBase)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        foreach ($grouped as $status => $count) {
            if (array_key_exists($status, $statusCounts)) {
                $statusCounts[$status] = (int) $count;
            }
        }

        $totalToday = array_sum($statusCounts);
        $issues = $statusCounts[PatrolSession::STATUS_INCOMPLETE] + $statusCounts[PatrolSession::STATUS_NOT_STARTED];

        $recent = (clone $todayBase)
            ->with(['members:id,patrol_session_id,security_name'])
            ->orderByDesc('started_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get([
                'id', 'work_date', 'status', 'started_at', 'ended_at',
                'post_name', 'unit_name', 'team_name', 'shift_name',
            ])
            ->map(fn (PatrolSession $session) => $this->patrolRecentItem($session))
            ->values()
            ->all();

        $trend = PatrolSession::query()
            ->whereDate('work_date', '>=', $periodStart)
            ->whereDate('work_date', '<=', $periodEnd)
            ->when($scope !== null, fn ($q) => $q->whereIn('unit_id', $scope))
            ->selectRaw('DATE(work_date) as day, status, COUNT(*) as aggregate')
            ->groupBy('day', 'status')
            ->orderBy('day')
            ->get();

        $trendByDay = [];
        foreach ($trend as $row) {
            $day = (string) $row->day;
            if (! isset($trendByDay[$day])) {
                $trendByDay[$day] = [
                    'date' => $day,
                    'label' => $this->formatShortDate($day),
                    'total' => 0,
                    'completed' => 0,
                ];
            }
            $count = (int) $row->aggregate;
            $trendByDay[$day]['total'] += $count;
            if ($row->status === PatrolSession::STATUS_COMPLETED) {
                $trendByDay[$day]['completed'] += $count;
            }
        }

        return [
            'today' => [
                'total' => $totalToday,
                'pending' => $statusCounts[PatrolSession::STATUS_PENDING],
                'active' => $statusCounts[PatrolSession::STATUS_ACTIVE],
                'completed' => $statusCounts[PatrolSession::STATUS_COMPLETED],
                'incomplete' => $statusCounts[PatrolSession::STATUS_INCOMPLETE],
                'not_started' => $statusCounts[PatrolSession::STATUS_NOT_STARTED],
                'issues' => $issues,
            ],
            'trend' => array_values($trendByDay),
            'recent' => $recent,
            'links' => [
                'index' => route('admin.patrol-session.index', ['date_from' => $today, 'date_to' => $today]),
            ],
        ];
    }

    private function patrolRecentItem(PatrolSession $session): array
    {
        $memberNames = $session->members
            ->pluck('security_name')
            ->filter()
            ->unique()
            ->values();

        $actor = $memberNames->isNotEmpty()
            ? $memberNames->take(2)->implode(', ').($memberNames->count() > 2 ? ' +' . ($memberNames->count() - 2) : '')
            : ($session->team_name ?: '-');

        return [
            'id' => $session->id,
            'actor' => $actor,
            'post_name' => $session->post_name,
            'unit_name' => $session->unit_name,
            'team_name' => $session->team_name,
            'shift_name' => $session->shift_name,
            'started_at' => AttendanceClock::formatWib($session->started_at, 'H:i'),
            'ended_at' => AttendanceClock::formatWib($session->ended_at, 'H:i'),
            'status' => $session->status,
            'status_label' => $this->patrolStatusLabel($session->status),
        ];
    }

    /**
     * @param  array<int,int>|null  $scope
     * @return array<string, mixed>
     */
    private function attendanceStats(?array $scope, string $today): array
    {
        $records = AttendanceRecord::query()
            ->whereDate('work_date', $today)
            ->when($scope !== null, fn ($q) => $q->whereIn('unit_id', $scope))
            ->get([
                'id', 'security_name', 'security_registration_number', 'security_post_name',
                'unit_name', 'security_team_name', 'work_date', 'shift_name', 'shift_type',
                'scheduled_start_at', 'scheduled_end_at', 'status', 'corrected_status',
                'check_in_at', 'check_out_at', 'corrected_check_in_at', 'corrected_check_out_at',
                'setting_check_in_open_minutes', 'setting_late_tolerance_minutes',
                'shift_end_day_offset', 'absent_marked_at',
            ]);

        $summary = [
            'total' => $records->count(),
            'working' => 0,
            'day_off' => 0,
            'scheduled' => 0,
            'pending' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'absent' => 0,
            'checked_in' => 0,
            'not_checked_in' => 0,
            'late' => 0,
        ];

        foreach ($records as $record) {
            $effective = $record->effectiveStatus();
            $isWorking = $record->isWorking();

            if ($isWorking) {
                $summary['working']++;
            } else {
                $summary['day_off']++;
            }

            if (isset($summary[$effective])) {
                $summary[$effective]++;
            }

            $checkIn = $record->effectiveCheckInAt();
            if ($isWorking) {
                if ($checkIn !== null) {
                    $summary['checked_in']++;
                    if ($record->isLate($checkIn)) {
                        $summary['late']++;
                    }
                } elseif (in_array($effective, [AttendanceRecord::STATUS_SCHEDULED, AttendanceRecord::STATUS_PENDING], true)) {
                    // working records without check-in that are still scheduled/pending (not concluded as absent)
                    $summary['not_checked_in']++;
                }
            }
        }

        $recent = $records
            ->filter(fn (AttendanceRecord $r) => $r->check_in_at !== null || $r->check_out_at !== null || $r->corrected_check_in_at !== null || $r->corrected_check_out_at !== null || $r->effectiveCheckInAt() !== null)
            ->sortByDesc(fn (AttendanceRecord $r) => $r->effectiveCheckOutAt()?->timestamp ?? $r->effectiveCheckInAt()?->timestamp ?? 0)
            ->take(6)
            ->map(function (AttendanceRecord $record) {
                $status = $record->effectiveStatus();

                return [
                    'id' => $record->id,
                    'security_name' => $record->security_name,
                    'registration_number' => $record->security_registration_number,
                    'post_name' => $record->security_post_name,
                    'unit_name' => $record->unit_name,
                    'shift_name' => $record->shift_name,
                    'check_in' => AttendanceClock::formatWib($record->effectiveCheckInAt(), 'H:i'),
                    'check_out' => AttendanceClock::formatWib($record->effectiveCheckOutAt(), 'H:i'),
                    'status' => $status,
                    'status_label' => AttendanceRecord::statusLabel($status),
                    'is_late' => $record->isLate(),
                ];
            })
            ->values()
            ->all();

        return [
            'today' => $summary,
            'recent' => $recent,
            'links' => [
                'index' => route('admin.attendance-record.index', ['date_from' => $today, 'date_to' => $today]),
            ],
        ];
    }

    /**
     * @param  array<int,int>|null  $scope
     * @return array<string, mixed>
     */
    private function scheduleStats(?array $scope, string $today): array
    {
        $base = Schedule::query()
            ->whereDate('date', $today)
            ->whereHas('schedulePeriod', function ($q) use ($scope) {
                if ($scope !== null) {
                    $q->whereIn('unit_id', $scope);
                }
            });

        $total = (clone $base)->count();

        $byShift = (clone $base)
            ->leftJoin('shifts', 'schedules.shift_id', '=', 'shifts.id')
            ->selectRaw("COALESCE(shifts.name, 'Tanpa Shift') as name, COALESCE(shifts.code, '-') as code, COUNT(*) as aggregate")
            ->groupBy('shifts.name', 'shifts.code')
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'code' => $row->code,
                'count' => (int) $row->aggregate,
            ])
            ->values()
            ->all();

        $items = (clone $base)
            ->with([
                'security:id,full_name,registration_number',
                'shift:id,name,code,type,start_time,end_time,end_day_offset',
                'schedulePeriod:id,security_team_id,security_post_id,unit_id,status',
                'schedulePeriod.securityTeam:id,name,code',
                'schedulePeriod.securityPost:id,name',
                'schedulePeriod.unit:id,unit_name',
            ])
            ->orderBy('id')
            ->limit(10)
            ->get()
            ->map(function (Schedule $schedule) {
                $period = $schedule->schedulePeriod;

                return [
                    'id' => $schedule->id,
                    'security_name' => $schedule->security?->full_name,
                    'registration_number' => $schedule->security?->registration_number,
                    'team_name' => $period?->securityTeam?->name,
                    'team_code' => $period?->securityTeam?->code,
                    'shift_name' => $schedule->shift?->name,
                    'shift_code' => $schedule->shift?->code,
                    'shift_time' => $this->formatShiftTime($schedule->shift),
                    'post_name' => $period?->securityPost?->name,
                    'unit_name' => $period?->unit?->unit_name,
                ];
            })
            ->values()
            ->all();

        $month = Carbon::parse($today, AttendanceClock::OPERATIONAL_TIMEZONE)->format('Y-m');

        return [
            'today' => [
                'date' => $today,
                'date_label' => $this->formatDateId($today),
                'total' => $total,
                'by_shift' => $byShift,
                'items' => $items,
            ],
            'links' => [
                'calendar' => route('admin.schedule-calendar.index', ['month' => $month]),
            ],
        ];
    }

    /**
     * @param  array<int,int>|null  $scope
     * @return array<string, mixed>
     */
    private function assetStats(?array $scope): array
    {
        $aparBase = Apar::query()->when($scope !== null, fn ($q) => $q->whereIn('unit_id', $scope));
        $hydrantBase = Hydrant::query()->when($scope !== null, fn ($q) => $q->whereIn('unit_id', $scope));

        $apar = [
            'total' => (clone $aparBase)->count(),
            'active' => (clone $aparBase)->where('is_active', true)->count(),
            'inactive' => (clone $aparBase)->where('is_active', false)->count(),
            'last_not_ok' => $this->assetsWithLatestInspectionResult(Apar::class, AparInspection::class, 'apar_id', $scope, 'not_ok'),
        ];

        $hydrant = [
            'total' => (clone $hydrantBase)->count(),
            'active' => (clone $hydrantBase)->where('is_active', true)->count(),
            'inactive' => (clone $hydrantBase)->where('is_active', false)->count(),
            'last_not_ok' => $this->assetsWithLatestInspectionResult(Hydrant::class, HydrantInspection::class, 'hydrant_id', $scope, 'not_ok'),
        ];

        return [
            'apar' => $apar,
            'hydrant' => $hydrant,
            'links' => [
                'apar' => route('admin.apar.index'),
                'hydrant' => route('admin.hydrant.index'),
            ],
        ];
    }

    /**
     * Count assets whose latest inspection (by inspected_at desc, id desc) has the given result.
     *
     * @param  array<int,int>|null  $scope
     */
    private function assetsWithLatestInspectionResult(string $assetModel, string $inspectionModel, string $foreignKey, ?array $scope, string $result): int
    {
        $table = (new $inspectionModel)->getTable();
        $assetTable = (new $assetModel)->getTable();

        $latestAt = $inspectionModel::query()
            ->selectRaw("$foreignKey, MAX(inspected_at) as max_inspected_at")
            ->groupBy($foreignKey);

        $latestIds = $inspectionModel::query()
            ->from($table.' as i1')
            ->joinSub($latestAt, 'latest', function ($join) use ($foreignKey) {
                $join->on("i1.$foreignKey", '=', "latest.$foreignKey")
                    ->on('i1.inspected_at', '=', 'latest.max_inspected_at');
            })
            ->selectRaw("MAX(i1.id) as id")
            ->groupBy("i1.$foreignKey");

        return $inspectionModel::query()
            ->from($table.' as i')
            ->joinSub($latestIds, 'latest_ids', fn ($join) => $join->on('i.id', '=', 'latest_ids.id'))
            ->join($assetTable.' as assets', 'assets.id', '=', "i.$foreignKey")
            ->when($scope !== null, fn ($q) => $q->whereIn('assets.unit_id', $scope))
            ->where('i.result', $result)
            ->count();
    }

    /**
     * @param  array<int,int>|null  $scope
     * @return array<string, mixed>
     */
    private function guestBookStats(?array $scope, string $today): array
    {
        $base = GuestVisit::query()
            ->when($scope !== null, fn ($q) => $q->whereIn('unit_id', $scope));

        [$todayStartUtc, $todayEndUtc] = $this->wibDayUtcBounds($today);

        $active = (clone $base)->whereNull('check_out_at')->count();
        $todayCount = (clone $base)->whereBetween('check_in_at', [$todayStartUtc, $todayEndUtc])->count();
        $checkedOutToday = (clone $base)->whereBetween('check_out_at', [$todayStartUtc, $todayEndUtc])->count();

        $recent = (clone $base)
            ->orderByDesc('check_in_at')
            ->limit(6)
            ->get([
                'id', 'full_name', 'company_name',
                'unit_name_snapshot', 'check_in_at', 'check_out_at',
            ])
            ->map(function (GuestVisit $visit) {
                $isActive = $visit->check_out_at === null;

                return [
                    'id' => $visit->id,
                    'full_name' => $visit->full_name,
                    'company_name' => $visit->company_name,
                    'unit_name' => $visit->unit_name_snapshot,
                    'check_in_at' => $this->formatWibTimestamp($visit->check_in_at),
                    'check_out_at' => $this->formatWibTimestamp($visit->check_out_at),
                    'status' => $isActive ? 'active' : 'checked_out',
                    'status_label' => $isActive ? 'Masih di Lokasi' : 'Sudah Pulang',
                ];
            })
            ->values()
            ->all();

        return [
            'today' => [
                'total' => $todayCount,
                'active' => $active,
                'checked_out' => $checkedOutToday,
            ],
            'recent' => $recent,
            'links' => [
                'index' => route('admin.guest-book.index'),
            ],
        ];
    }

    /**
     * @param  array<int,int>|null  $scope
     * @return array<int, array<string, mixed>>
     */
    private function recentActivities(?array $scope, string $periodStart, string $periodEnd): array
    {
        $activities = collect();
        [$startUtc, $endUtc] = $this->wibRangeUtcBounds($periodStart, $periodEnd);

        // Patrol session events (started/ended) within period
        $sessions = PatrolSession::query()
            ->when($scope !== null, fn ($q) => $q->whereIn('unit_id', $scope))
            ->where(function ($q) use ($startUtc, $endUtc) {
                $q->whereBetween('started_at', [$startUtc, $endUtc])
                    ->orWhereBetween('ended_at', [$startUtc, $endUtc]);
            })
            ->orderByRaw('COALESCE(ended_at, started_at) DESC')
            ->limit(10)
            ->get(['id', 'status', 'started_at', 'ended_at', 'post_name', 'unit_name', 'team_name']);

        foreach ($sessions as $s) {
            $at = $s->ended_at ?? $s->started_at;
            if (! $at) {
                continue;
            }
            $desc = $s->ended_at ? 'Sesi patroli selesai' : 'Sesi patroli dimulai';
            $activities->push($this->activityItem(
                'patrol',
                'Patroli',
                $s->team_name ?: ($s->post_name ?: '-'),
                trim($desc.' · '.collect([$s->post_name, $s->unit_name])->filter()->implode(' - ')),
                $at
            ));
        }

        // Attendance check-in/out events (by work_date range; events shown if timestamp exists)
        $attendance = AttendanceRecord::query()
            ->whereDate('work_date', '>=', $periodStart)
            ->whereDate('work_date', '<=', $periodEnd)
            ->when($scope !== null, fn ($q) => $q->whereIn('unit_id', $scope))
            ->where(function ($q) {
                $q->whereNotNull('check_in_at')->orWhereNotNull('check_out_at')
                    ->orWhereNotNull('corrected_check_in_at')->orWhereNotNull('corrected_check_out_at');
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get([
                'id', 'security_name', 'security_post_name', 'unit_name', 'status', 'corrected_status',
                'check_in_at', 'check_out_at', 'corrected_check_in_at', 'corrected_check_out_at',
                'shift_type', 'scheduled_start_at', 'scheduled_end_at', 'setting_check_in_open_minutes',
                'setting_late_tolerance_minutes', 'shift_end_day_offset', 'absent_marked_at',
            ]);

        foreach ($attendance as $r) {
            $out = $r->effectiveCheckOutAt();
            $in = $r->effectiveCheckInAt();
            $loc = collect([$r->security_post_name, $r->unit_name])->filter()->implode(' - ');
            if ($out && $this->carbonInWibRange($out, $periodStart, $periodEnd)) {
                $activities->push($this->activityItem('attendance', 'Absensi', $r->security_name ?: '-', 'Check-out'.($loc ? ' · '.$loc : ''), $out));
            } elseif ($in && $this->carbonInWibRange($in, $periodStart, $periodEnd)) {
                $activities->push($this->activityItem('attendance', 'Absensi', $r->security_name ?: '-', 'Check-in'.($loc ? ' · '.$loc : ''), $in));
            }
        }

        // APAR inspections
        $aparInspections = AparInspection::query()
            ->with(['apar:id,apar_code,placement', 'security:id,full_name'])
            ->whereBetween('inspected_at', [$startUtc, $endUtc])
            ->when($scope !== null, fn ($q) => $q->whereIn('unit_id', $scope))
            ->orderByDesc('inspected_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'apar_id', 'security_id', 'inspected_at', 'result']);

        foreach ($aparInspections as $i) {
            if (! $i->inspected_at) {
                continue;
            }
            $actor = $i->security?->full_name ?: 'Satpam';
            $asset = $i->apar?->apar_code ?: 'APAR';
            $result = $i->result === 'not_ok' ? 'Tidak OK' : 'OK';
            $activities->push($this->activityItem('inspection_apar', 'Inspeksi APAR', $actor, $asset.' · Hasil: '.$result, $i->inspected_at));
        }

        // Hydrant inspections
        $hydrantInspections = HydrantInspection::query()
            ->with(['hydrant:id,hydrant_code,name', 'security:id,full_name'])
            ->whereBetween('inspected_at', [$startUtc, $endUtc])
            ->when($scope !== null, fn ($q) => $q->whereIn('unit_id', $scope))
            ->orderByDesc('inspected_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'hydrant_id', 'security_id', 'inspected_at', 'result']);

        foreach ($hydrantInspections as $i) {
            if (! $i->inspected_at) {
                continue;
            }
            $actor = $i->security?->full_name ?: 'Satpam';
            $asset = $i->hydrant?->hydrant_code ?: ($i->hydrant?->name ?: 'Hydrant');
            $result = $i->result === 'not_ok' ? 'Tidak OK' : 'OK';
            $activities->push($this->activityItem('inspection_hydrant', 'Inspeksi Hydrant', $actor, $asset.' · Hasil: '.$result, $i->inspected_at));
        }

        // Guest visits (check-in / check-out in period)
        $visits = GuestVisit::query()
            ->when($scope !== null, fn ($q) => $q->whereIn('unit_id', $scope))
            ->where(function ($q) use ($startUtc, $endUtc) {
                $q->whereBetween('check_in_at', [$startUtc, $endUtc])
                    ->orWhere(function ($q2) use ($startUtc, $endUtc) {
                        $q2->whereNotNull('check_out_at')->whereBetween('check_out_at', [$startUtc, $endUtc]);
                    });
            })
            ->orderByDesc('check_in_at')
            ->limit(12)
            ->get(['id', 'full_name', 'company_name', 'unit_name_snapshot', 'check_in_at', 'check_out_at']);

        foreach ($visits as $v) {
            if ($v->check_out_at && $this->carbonInWibRange($v->check_out_at, $periodStart, $periodEnd)) {
                $activities->push($this->activityItem(
                    'guest_checkout',
                    'Kunjungan Tamu',
                    $v->full_name ?: 'Tamu',
                    'Check-out'.($v->company_name ? ' · '.$v->company_name : ''),
                    $v->check_out_at
                ));
            }
            if ($v->check_in_at && $this->carbonInWibRange($v->check_in_at, $periodStart, $periodEnd)) {
                $activities->push($this->activityItem(
                    'guest_checkin',
                    'Kunjungan Tamu',
                    $v->full_name ?: 'Tamu',
                    'Check-in'.($v->company_name ? ' · '.$v->company_name : '').($v->unit_name_snapshot ? ' · '.$v->unit_name_snapshot : ''),
                    $v->check_in_at
                ));
            }
        }

        return $activities
            ->filter(fn ($a) => ! empty($a['at_ts']))
            ->sortByDesc('at_ts')
            ->unique(fn ($a) => $a['type'].'|'.$a['at_ts'].'|'.$a['actor'].'|'.$a['description'])
            ->take(10)
            ->values()
            ->map(function ($a) {
                unset($a['at_ts']);

                return $a;
            })
            ->all();
    }

    /**
     * @return array{0:Carbon,1:Carbon}
     */
    private function wibDayUtcBounds(string $wibDate): array
    {
        $start = Carbon::parse($wibDate, AttendanceClock::OPERATIONAL_TIMEZONE)->startOfDay();
        $end = Carbon::parse($wibDate, AttendanceClock::OPERATIONAL_TIMEZONE)->endOfDay();

        return [$start->copy()->utc(), $end->copy()->utc()];
    }

    /**
     * @return array{0:Carbon,1:Carbon}
     */
    private function wibRangeUtcBounds(string $startWibDate, string $endWibDate): array
    {
        $start = Carbon::parse($startWibDate, AttendanceClock::OPERATIONAL_TIMEZONE)->startOfDay();
        $end = Carbon::parse($endWibDate, AttendanceClock::OPERATIONAL_TIMEZONE)->endOfDay();

        return [$start->copy()->utc(), $end->copy()->utc()];
    }

    private function carbonInWibRange(Carbon $at, string $startWibDate, string $endWibDate): bool
    {
        $d = $at->copy()->timezone(AttendanceClock::OPERATIONAL_TIMEZONE)->toDateString();

        return $d >= $startWibDate && $d <= $endWibDate;
    }

    private function formatWibTimestamp(?Carbon $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value->copy()->timezone(AttendanceClock::OPERATIONAL_TIMEZONE)->format('d M Y, H:i');
    }

    /**
     * @return array<string, mixed>
     */
    private function activityItem(string $type, string $typeLabel, string $actor, string $description, Carbon $at): array
    {
        $wib = $at->copy()->timezone(AttendanceClock::OPERATIONAL_TIMEZONE);

        return [
            'type' => $type,
            'type_label' => $typeLabel,
            'actor' => $actor,
            'description' => $description,
            'at' => $wib->format('Y-m-d H:i:s'),
            'at_label' => $wib->format('d M Y, H:i'),
            'at_ts' => $wib->timestamp,
        ];
    }

    private function formatShiftTime($shift): ?string
    {
        if ($shift === null || ! $shift->start_time || ! $shift->end_time) {
            return null;
        }

        $s = substr((string) $shift->start_time, 0, 5);
        $e = substr((string) $shift->end_time, 0, 5);
        $offset = (int) ($shift->end_day_offset ?? 0);

        return $s.' - '.$e.($offset > 0 ? ' (+1 hari)' : '');
    }

    private function formatDateId(string $date): string
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $c = Carbon::parse($date, AttendanceClock::OPERATIONAL_TIMEZONE);

        return $c->day.' '.$months[$c->month].' '.$c->year;
    }

    private function formatShortDate(string $date): string
    {
        $c = Carbon::parse($date, AttendanceClock::OPERATIONAL_TIMEZONE);

        return $c->format('d/m');
    }

    private function patrolStatusLabel(string $status): string
    {
        return match ($status) {
            PatrolSession::STATUS_PENDING => 'Menunggu',
            PatrolSession::STATUS_ACTIVE => 'Aktif',
            PatrolSession::STATUS_COMPLETED => 'Selesai',
            PatrolSession::STATUS_INCOMPLETE => 'Tidak Lengkap',
            PatrolSession::STATUS_NOT_STARTED => 'Tidak Dimulai',
            default => ucfirst($status),
        };
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

        if ($user->role !== 'admin' || $user->unit_id === null) {
            return [];
        }

        return array_map('intval', $user->unit?->descendantIds() ?? [$user->unit_id]);
    }
}
