<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesAttendanceScope;
use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceGenerationAnomaly;
use App\Models\AttendanceRecord;
use App\Models\SecurityPost;
use App\Models\Shift;
use App\Support\AttendanceClock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceRecordController extends Controller
{
    use ResolvesAttendanceScope;

    public function index(Request $request): Response
    {
        $scope = $this->attendanceScope($request);
        $search = trim((string) $request->query('search', ''));

        $sortColumns = [
            'work_date' => 'attendance_records.work_date',
            'security' => 'attendance_records.security_name',
            'status' => 'attendance_records.status',
            'unit' => 'attendance_records.unit_name',
        ];

        $sort = (string) $request->query('sort', 'work_date');
        if (! array_key_exists($sort, $sortColumns)) {
            $sort = 'work_date';
        }
        $dir = $request->query('dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $status = (string) $request->query('status', '');
        $statusOptions = $this->statusOptions();
        if (! array_key_exists($status, $statusOptions)) {
            $status = '';
        }

        $unitId = $this->attendanceFilterUnitId($request, $scope);
        $postId = $this->queryInt($request, 'security_post_id');
        $periodId = $this->queryInt($request, 'period_id');
        $dateFrom = $this->queryDate($request, 'date_from');
        $dateTo = $this->queryDate($request, 'date_to');
        $isDefaultDateFilter = ! $request->query->has('date_from') && ! $request->query->has('date_to');

        if ($isDefaultDateFilter) {
            $today = AttendanceClock::todayWib();
            $dateFrom = $today;
            $dateTo = $today;
        }

        $now = AttendanceClock::nowUtc();

        $query = AttendanceRecord::query()
            ->with(['corrections' => fn ($query) => $query->where('status', AttendanceCorrection::STATUS_PENDING)])
            ->when($scope !== null, fn ($query) => $query->whereIn('attendance_records.unit_id', $scope))
            ->when($unitId !== null, fn ($query) => $query->where('attendance_records.unit_id', $unitId))
            ->when($postId !== null, fn ($query) => $query->where('attendance_records.security_post_id', $postId))
            ->when($periodId !== null, fn ($query) => $query->where('attendance_records.schedule_period_id', $periodId))
            ->when($status !== '', fn ($query) => $this->applyEffectiveStatusFilter($query, $status, $now))
            ->when($dateFrom !== null || $dateTo !== null, function ($query) use ($dateFrom, $dateTo, $isDefaultDateFilter, $now) {
                $query->where(function ($query) use ($dateFrom, $dateTo, $isDefaultDateFilter, $now) {
                    $query->when($dateFrom !== null, fn ($q) => $q->whereDate('attendance_records.work_date', '>=', $dateFrom))
                        ->when($dateTo !== null, fn ($q) => $q->whereDate('attendance_records.work_date', '<=', $dateTo));

                    if ($isDefaultDateFilter) {
                        $query->orWhere(function ($q) use ($now) {
                            $this->applyUnresolvedOverdueCheckoutFilter($q, $now);
                        });
                    }
                });
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('attendance_records.security_name', 'like', "%{$search}%")
                        ->orWhere('attendance_records.security_registration_number', 'like', "%{$search}%")
                        ->orWhere('attendance_records.security_post_name', 'like', "%{$search}%")
                        ->orWhere('attendance_records.unit_name', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortColumns[$sort], $dir)
            ->orderBy('attendance_records.id', 'desc');

        $paginator = $query->paginate(50)->withQueryString();

        return Inertia::render('Admin/Attendance/Records/Index', [
            'records' => $paginator->getCollection()->map(fn (AttendanceRecord $record) => $this->listItem($record)),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'statuses' => collect($statusOptions)
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'units' => $this->attendanceUnits($scope),
            'posts' => SecurityPost::query()
                ->when($scope !== null, fn ($query) => $query->whereIn('unit_id', $scope))
                ->when($unitId !== null, fn ($query) => $query->where('unit_id', $unitId))
                ->orderBy('name')
                ->get(['id', 'name', 'unit_id'])
                ->map(fn (SecurityPost $post) => [
                    'value' => $post->id,
                    'label' => $post->name,
                    'unit_id' => $post->unit_id,
                ]),
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'dir' => $dir,
                'status' => $status,
                'unit_id' => $unitId ?? '',
                'security_post_id' => $postId ?? '',
                'period_id' => $periodId ?? '',
                'date_from' => $dateFrom ?? '',
                'date_to' => $dateTo ?? '',
            ],
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $record = AttendanceRecord::query()
            ->with([
                'corrections' => fn ($query) => $query->orderByDesc('id'),
                'corrections.requester:id,name',
                'corrections.reviewer:id,name',
                'shift:id,name,code,type,start_time,end_time,end_day_offset',
            ])
            ->findOrFail($id);

        $this->assertAttendanceUnit($this->attendanceScope($request), (int) $record->unit_id);

        $anomalies = AttendanceGenerationAnomaly::query()
            ->with('resolver:id,name')
            ->where('schedule_id', $record->schedule_id)
            ->orderByDesc('detected_at')
            ->get()
            ->map(fn (AttendanceGenerationAnomaly $anomaly) => [
                'id' => $anomaly->id,
                'type_label' => $anomaly->typeLabel(),
                'details' => $anomaly->details,
                'detected_at' => AttendanceClock::formatWib($anomaly->detected_at),
                'resolved_at' => AttendanceClock::formatWib($anomaly->resolved_at),
                'resolved_by' => $anomaly->resolver?->name,
                'resolution_note' => $anomaly->resolution_note,
                'is_active' => $anomaly->isActive(),
            ]);

        return Inertia::render('Admin/Attendance/Records/Show', [
            'record' => $this->detailItem($record),
            'corrections' => $record->corrections->map(fn (AttendanceCorrection $correction) => $this->correctionItem($correction)),
            'anomalies' => $anomalies,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function listItem(AttendanceRecord $record): array
    {
        $status = $record->effectiveDisplayStatus();

        return [
            'id' => $record->id,
            'schedule_id' => $record->schedule_id,
            'security_name' => $record->security_name,
            'registration_number' => $record->security_registration_number,
            'unit_name' => $record->unit_name,
            'post_name' => $record->security_post_name,
            'team_name' => $record->security_team_name,
            'work_date' => $record->work_date?->toDateString(),
            'work_date_label' => $record->work_date?->format('d M Y'),
            'shift_name' => $record->shift_name,
            'shift_type' => $record->shift_type,
            'scheduled_start' => AttendanceClock::formatWib($record->scheduled_start_at, 'H:i'),
            'scheduled_end' => AttendanceClock::formatWib($record->scheduled_end_at, 'H:i'),
            'check_in' => AttendanceClock::formatWib($record->effectiveCheckInAt(), 'H:i'),
            'check_out' => AttendanceClock::formatWib($record->effectiveCheckOutAt(), 'H:i'),
            'status' => $status,
            'status_label' => AttendanceRecord::displayStatusLabel($status),
            'is_late' => $record->isLate(),
            'late_minutes' => $record->lateMinutes(),
            'late_label' => $record->lateLabel(),
            'has_pending_correction' => $record->hasPendingCorrection(),
            'show_url' => route('admin.attendance-record.show', $record->id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailItem(AttendanceRecord $record): array
    {
        $status = $record->effectiveDisplayStatus();

        return [
            'id' => $record->id,
            'schedule_id' => $record->schedule_id,
            'security_name' => $record->security_name,
            'registration_number' => $record->security_registration_number,
            'unit_name' => $record->unit_name,
            'post_name' => $record->security_post_name,
            'team_name' => $record->security_team_name,
            'work_date' => $record->work_date?->toDateString(),
            'work_date_label' => $record->work_date?->format('d M Y'),
            'shift_name' => $record->shift_name,
            'shift_code' => $record->shift_code,
            'shift_type' => $record->shift_type,
            'shift_time' => $record->shift_start_time !== null
                ? substr((string) $record->shift_start_time, 0, 5)
                    .' - '.substr((string) $record->shift_end_time, 0, 5)
                    .($record->shift_end_day_offset ? ' (+1 hari)' : '')
                : null,
            'scheduled_start' => AttendanceClock::formatWib($record->scheduled_start_at),
            'scheduled_end' => AttendanceClock::formatWib($record->scheduled_end_at),
            'status' => $status,
            'status_label' => AttendanceRecord::displayStatusLabel($status),
            'stored_status_label' => AttendanceRecord::statusLabel($record->status),
            'is_late' => $record->isLate(),
            'late_minutes' => $record->lateMinutes(),
            'late_label' => $record->lateLabel(),
            'is_locked' => $record->isLocked(),
            'snapshot_locked_at' => AttendanceClock::formatWib($record->snapshot_locked_at),
            'last_synced_at' => AttendanceClock::formatWib($record->last_synced_at),
            'absent_marked_at' => AttendanceClock::formatWib($record->absent_marked_at),
            'check_in' => $this->activity($record, 'check_in'),
            'check_out' => $this->activity($record, 'check_out'),
            'corrected_check_in' => AttendanceClock::formatWib($record->corrected_check_in_at),
            'corrected_check_out' => AttendanceClock::formatWib($record->corrected_check_out_at),
            'corrected_status_label' => $record->corrected_status !== null
                ? AttendanceRecord::statusLabel($record->corrected_status)
                : null,
            'settings' => [
                'gps_radius_meters' => $record->setting_gps_radius_meters,
                'check_in_open_minutes' => $record->setting_check_in_open_minutes,
                'late_tolerance_minutes' => $record->setting_late_tolerance_minutes,
                'check_in_tolerance_minutes' => $record->checkInToleranceMinutes(),
                'early_checkout_tolerance_minutes' => $record->setting_early_checkout_tolerance_minutes,
                'checkout_close_minutes' => $record->setting_checkout_close_minutes,
                'max_gps_accuracy_meters' => $record->setting_max_gps_accuracy_meters,
            ],
            'post_coordinates' => [
                'latitude' => $record->post_latitude,
                'longitude' => $record->post_longitude,
            ],
            'can_request_correction' => ! $record->hasPendingCorrection(),
            'back_url' => route('admin.attendance-record.index'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function activity(AttendanceRecord $record, string $prefix): ?array
    {
        $at = $record->getAttribute($prefix.'_at');

        if ($at === null) {
            return null;
        }

        return [
            'at' => AttendanceClock::formatWib($at),
            'captured_at' => AttendanceClock::formatWib($record->getAttribute($prefix.'_captured_at')),
            'latitude' => $record->getAttribute($prefix.'_latitude'),
            'longitude' => $record->getAttribute($prefix.'_longitude'),
            'accuracy_meters' => $record->getAttribute($prefix.'_accuracy_meters'),
            'distance_meters' => $record->getAttribute($prefix.'_distance_meters'),
            'geofence_valid' => $record->getAttribute($prefix.'_geofence_valid'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function correctionItem(AttendanceCorrection $correction): array
    {
        return [
            'id' => $correction->id,
            'status' => $correction->status,
            'status_label' => $correction->statusLabel(),
            'reason' => $correction->reason,
            'requested_by' => $correction->requester?->name,
            'requested_by_role' => $correction->requested_by_role,
            'requested_check_in' => AttendanceClock::formatWib($correction->requested_check_in_at),
            'requested_check_out' => AttendanceClock::formatWib($correction->requested_check_out_at),
            'requested_status' => $correction->requested_status !== null
                ? AttendanceRecord::statusLabel($correction->requested_status)
                : null,
            'requested_at' => AttendanceClock::formatWib($correction->created_at),
            'reviewed_by' => $correction->reviewer?->name,
            'reviewed_at' => AttendanceClock::formatWib($correction->reviewed_at),
            'review_note' => $correction->review_note,
            'before_check_in' => AttendanceClock::formatWib($correction->before_check_in_at),
            'before_check_out' => AttendanceClock::formatWib($correction->before_check_out_at),
            'before_status' => $correction->before_status !== null
                ? AttendanceRecord::statusLabel($correction->before_status)
                : null,
            'applied_check_in' => AttendanceClock::formatWib($correction->applied_check_in_at),
            'applied_check_out' => AttendanceClock::formatWib($correction->applied_check_out_at),
            'applied_status' => $correction->applied_status !== null
                ? AttendanceRecord::statusLabel($correction->applied_status)
                : null,
        ];
    }

    private function applyEffectiveStatusFilter(Builder $query, string $status, Carbon $now): void
    {
        $query->where(function (Builder $query) use ($status, $now) {
            $query->where(function (Builder $query) use ($status, $now) {
                $query->whereNotNull('attendance_records.corrected_status');
                $this->applyStoredStatusFilter($query, $status, $now, 'attendance_records.corrected_status');
            })->orWhere(function (Builder $query) use ($status, $now) {
                $query->whereNull('attendance_records.corrected_status');
                $this->applyStoredStatusFilter($query, $status, $now, 'attendance_records.status');
            });
        });
    }

    private function applyStoredStatusFilter(Builder $query, string $status, Carbon $now, string $statusColumn): void
    {
        $nowString = $now->format('Y-m-d H:i:s');
        $opensAt = $this->checkInOpensAtExpression($query);
        $absenceDeadline = $this->absenceDeadlineExpression($query);
        $checkIn = "COALESCE(attendance_records.corrected_check_in_at, attendance_records.check_in_at)";
        $checkOut = "COALESCE(attendance_records.corrected_check_out_at, attendance_records.check_out_at)";

        if ($status === AttendanceRecord::STATUS_DAY_OFF) {
            $query->where(function (Builder $query) use ($statusColumn) {
                $query->where($statusColumn, AttendanceRecord::STATUS_DAY_OFF)
                    ->orWhere('attendance_records.shift_type', Shift::TYPE_DAY_OFF);
            });

            return;
        }

        $query->where($statusColumn, '!=', AttendanceRecord::STATUS_DAY_OFF)
            ->where('attendance_records.shift_type', Shift::TYPE_WORKING);

        if ($status === AttendanceRecord::STATUS_ABSENT) {
            $query->where(function (Builder $query) use ($statusColumn, $checkIn, $absenceDeadline, $nowString) {
                $query->where($statusColumn, AttendanceRecord::STATUS_ABSENT)
                    ->orWhere(function (Builder $query) use ($statusColumn, $checkIn, $absenceDeadline, $nowString) {
                        $query->where($statusColumn, AttendanceRecord::STATUS_SCHEDULED)
                            ->whereRaw("{$checkIn} IS NULL")
                            ->whereNotNull('attendance_records.scheduled_end_at')
                            ->whereRaw("{$absenceDeadline} < ?", [$nowString]);
                    });
            });

            return;
        }

        if ($status === AttendanceRecord::STATUS_COMPLETED) {
            $query->where(function (Builder $query) use ($statusColumn, $checkIn, $checkOut) {
                $query->where($statusColumn, AttendanceRecord::STATUS_COMPLETED)
                    ->orWhere(function (Builder $query) use ($checkIn, $checkOut) {
                        $query->whereRaw("{$checkIn} IS NOT NULL")
                            ->whereRaw("{$checkOut} IS NOT NULL");
                    });
            });

            return;
        }

        if ($status === 'overdue_checkout') {
            $query->whereRaw("{$checkIn} IS NOT NULL")
                ->whereRaw("{$checkOut} IS NULL")
                ->whereNotNull('attendance_records.scheduled_end_at')
                ->where('attendance_records.scheduled_end_at', '<', $nowString);

            return;
        }

        if ($status === AttendanceRecord::STATUS_IN_PROGRESS) {
            $query->whereRaw("{$checkIn} IS NOT NULL")
                ->whereRaw("{$checkOut} IS NULL")
                ->where(function (Builder $query) use ($nowString) {
                    $query->whereNull('attendance_records.scheduled_end_at')
                        ->orWhere('attendance_records.scheduled_end_at', '>=', $nowString);
                });

            return;
        }

        $query->whereRaw("{$checkIn} IS NULL")
            ->where(function (Builder $query) use ($absenceDeadline, $nowString) {
                $query->whereNull('attendance_records.scheduled_end_at')
                    ->orWhereRaw("{$absenceDeadline} >= ?", [$nowString]);
            });

        if ($status === AttendanceRecord::STATUS_PENDING) {
            $query->whereNotNull('attendance_records.scheduled_start_at')
                ->whereRaw("{$opensAt} <= ?", [$nowString]);

            return;
        }

        $query->where(function (Builder $query) use ($opensAt, $nowString) {
            $query->whereNull('attendance_records.scheduled_start_at')
                ->orWhereRaw("{$opensAt} > ?", [$nowString]);
        });
    }

    /**
     * @return array<string, string>
     */
    private function statusOptions(): array
    {
        return [
            AttendanceRecord::STATUS_SCHEDULED => 'Belum Waktunya Absen',
            AttendanceRecord::STATUS_PENDING => 'Belum Absen Masuk',
            AttendanceRecord::STATUS_IN_PROGRESS => 'Hadir',
            'overdue_checkout' => 'Belum Absen Keluar',
            AttendanceRecord::STATUS_COMPLETED => 'Selesai',
            AttendanceRecord::STATUS_DAY_OFF => 'Libur',
            AttendanceRecord::STATUS_ABSENT => 'Tidak Hadir',
        ];
    }

    private function applyUnresolvedOverdueCheckoutFilter(Builder $query, Carbon $now): void
    {
        $nowString = $now->format('Y-m-d H:i:s');
        $checkIn = "COALESCE(attendance_records.corrected_check_in_at, attendance_records.check_in_at)";
        $checkOut = "COALESCE(attendance_records.corrected_check_out_at, attendance_records.check_out_at)";

        $query->where('attendance_records.shift_type', Shift::TYPE_WORKING)
            ->whereRaw("{$checkIn} IS NOT NULL")
            ->whereRaw("{$checkOut} IS NULL")
            ->where(function (Builder $query) use ($nowString) {
                $query->whereNull('attendance_records.corrected_status')
                    ->orWhere('attendance_records.corrected_status', '!=', AttendanceRecord::STATUS_DAY_OFF);
            })
            ->where(function (Builder $query) use ($nowString) {
                $query->where(function (Builder $q) use ($nowString) {
                    $q->whereNotNull('attendance_records.scheduled_end_at')
                        ->where('attendance_records.scheduled_end_at', '<', $nowString);
                })->orWhere(function (Builder $q) use ($nowString) {
                    $q->whereNotNull('attendance_records.scheduled_end_at')
                        ->whereRaw($this->absenceDeadlineExpression($q).' < ?', [$nowString]);
                });
            });
    }

    private function checkInOpensAtExpression(Builder $query): string
    {
        if ($query->getConnection()->getDriverName() === 'sqlite') {
            return "datetime(attendance_records.scheduled_start_at, '-' || attendance_records.setting_check_in_open_minutes || ' minutes')";
        }

        return 'DATE_SUB(attendance_records.scheduled_start_at, INTERVAL attendance_records.setting_check_in_open_minutes MINUTE)';
    }

    private function absenceDeadlineExpression(Builder $query): string
    {
        if ($query->getConnection()->getDriverName() === 'sqlite') {
            return "datetime(attendance_records.scheduled_end_at, '+24 hours')";
        }

        return 'DATE_ADD(attendance_records.scheduled_end_at, INTERVAL 24 HOUR)';
    }

    private function queryInt(Request $request, string $key): ?int
    {
        $raw = $request->query($key);

        return is_numeric($raw) ? (int) $raw : null;
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
