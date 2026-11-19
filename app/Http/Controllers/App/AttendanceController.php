<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\App\Concerns\BuildsAttendanceContext;
use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\Security;
use App\Models\Shift;
use App\Services\AttendanceActivityService;
use App\Services\AttendanceCorrectionService;
use App\Support\AttendanceClock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    use BuildsAttendanceContext;

    public function index(Request $request): Response
    {
        return Inertia::render('App/Attendance/Index', $this->attendanceContext($request));
    }

    public function history(Request $request): Response
    {
        $nowWib = AttendanceClock::nowWib();
        $nowUtc = AttendanceClock::nowUtc();

        $month = (int) $request->query('month', (int) $nowWib->month);
        $year = (int) $request->query('year', (int) $nowWib->year);
        if ($month < 1 || $month > 12) {
            $month = (int) $nowWib->month;
        }
        if ($year < 2000 || $year > 2100) {
            $year = (int) $nowWib->year;
        }

        $status = (string) $request->query('status', '');
        if (! array_key_exists($status, AttendanceRecord::STATUS_LABELS)) {
            $status = '';
        }

        $security = Security::query()
            ->where('user_id', $request->user()->id)
            ->first();

        $statuses = collect(AttendanceRecord::STATUS_LABELS)
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values();

        $baseProps = [
            'filters' => [
                'month' => $month,
                'year' => $year,
                'status' => $status,
            ],
            'statuses' => $statuses,
            'has_security_profile' => $security !== null,
        ];

        if ($security === null) {
            return Inertia::render('App/Attendance/History', array_merge($baseProps, [
                'records' => [],
                'summary' => ['total' => 0],
            ]));
        }

        $records = AttendanceRecord::query()
            ->forSecurity($security->id)
            ->with(['corrections' => fn ($q) => $q->latest('id')])
            ->whereMonth('work_date', $month)
            ->whereYear('work_date', $year)
            ->when($status !== '', fn (Builder $q) => $this->applyEffectiveStatusFilter($q, $status, $nowUtc))
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->get()
            ->map(function (AttendanceRecord $record) use ($nowUtc) {
                $effectiveStatus = $record->effectiveStatus($nowUtc);
                $checkIn = $record->effectiveCheckInAt();
                $checkOut = $record->effectiveCheckOutAt();
                $totalMinutes = ($checkIn && $checkOut) ? $checkIn->diffInMinutes($checkOut) : null;

                $shiftStart = $record->shift_start_time ? substr((string) $record->shift_start_time, 0, 5) : null;
                $shiftEnd = $record->shift_end_time ? substr((string) $record->shift_end_time, 0, 5) : null;
                $shiftTime = null;
                if ($shiftStart && $shiftEnd) {
                    $suffix = ((int) $record->shift_end_day_offset) ? ' (+1 hari)' : '';
                    $shiftTime = $shiftStart.' - '.$shiftEnd.$suffix;
                }

                $latestCorrection = $record->corrections->first();
                $pendingCorrection = $record->corrections->firstWhere('status', AttendanceCorrection::STATUS_PENDING);
                $hasPendingCorrection = $pendingCorrection !== null;

                return [
                    'id' => $record->id,
                    'work_date' => $record->work_date?->toDateString(),
                    'work_date_label' => $record->work_date
                        ? Carbon::parse($record->work_date->toDateString(), AttendanceClock::OPERATIONAL_TIMEZONE)
                            ->translatedFormat('d M Y')
                        : '-',
                    'shift_name' => $record->shift_name,
                    'shift_time' => $shiftTime,
                    'post_name' => $record->security_post_name,
                    'unit_name' => $record->unit_name,
                    'check_in_label' => AttendanceClock::formatWib($checkIn, 'H:i'),
                    'check_out_label' => AttendanceClock::formatWib($checkOut, 'H:i'),
                    'status' => $effectiveStatus,
                    'status_label' => AttendanceRecord::statusLabel($effectiveStatus),
                    'is_late' => $record->isLate($checkIn ?? $nowUtc),
                    'total_work_minutes' => $totalMinutes,
                    'total_work_label' => $totalMinutes !== null
                        ? sprintf('%d jam %d menit', intdiv($totalMinutes, 60), $totalMinutes % 60)
                        : null,
                    'has_pending_correction' => $hasPendingCorrection,
                    'latest_correction_status' => $latestCorrection?->status,
                    'latest_correction_status_label' => $latestCorrection?->statusLabel(),
                    'can_request_correction' => ! $hasPendingCorrection,
                ];
            })->values();

        return Inertia::render('App/Attendance/History', array_merge($baseProps, [
            'records' => $records,
            'summary' => [
                'total' => $records->count(),
            ],
        ]));
    }

    public function correction(Request $request): Response
    {
        $security = Security::query()
            ->where('user_id', $request->user()->id)
            ->first();

        $nowWib = AttendanceClock::nowWib();
        $month = (int) $request->query('month', (int) $nowWib->month);
        $year = (int) $request->query('year', (int) $nowWib->year);
        if ($month < 1 || $month > 12) {
            $month = (int) $nowWib->month;
        }
        if ($year < 2000 || $year > 2100) {
            $year = (int) $nowWib->year;
        }

        $base = [
            'has_security_profile' => $security !== null,
            'selected_record_id' => null,
            'record' => null,
            'records' => [],
            'filters' => [
                'month' => $month,
                'year' => $year,
            ],
            'correctable_statuses' => collect(AttendanceRecord::CORRECTABLE_STATUSES)
                ->map(fn (string $value) => [
                    'value' => $value,
                    'label' => AttendanceRecord::statusLabel($value),
                ])->values(),
        ];

        if ($security === null) {
            return Inertia::render('App/Attendance/Correction', $base);
        }

        $nowUtc = AttendanceClock::nowUtc();
        $candidates = AttendanceRecord::query()
            ->forSecurity($security->id)
            ->with(['corrections' => fn ($q) => $q->latest('id')])
            ->whereMonth('work_date', $month)
            ->whereYear('work_date', $year)
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->get();

        $records = $candidates->map(function (AttendanceRecord $record) use ($nowUtc) {
            $effectiveStatus = $record->effectiveStatus($nowUtc);
            $checkIn = $record->effectiveCheckInAt();
            $checkOut = $record->effectiveCheckOutAt();
            $pending = $record->corrections->firstWhere('status', AttendanceCorrection::STATUS_PENDING) !== null;
            $latest = $record->corrections->first();
            $workDate = $record->work_date?->toDateString();
            $dateLabel = $workDate
                ? Carbon::parse($workDate, AttendanceClock::OPERATIONAL_TIMEZONE)->translatedFormat('d M Y')
                : '-';

            return [
                'id' => $record->id,
                'work_date' => $workDate,
                'work_date_label' => $dateLabel,
                'shift_name' => $record->shift_name,
                'post_name' => $record->security_post_name,
                'unit_name' => $record->unit_name,
                'status' => $effectiveStatus,
                'status_label' => AttendanceRecord::statusLabel($effectiveStatus),
                'check_in_label' => AttendanceClock::formatWib($checkIn, 'H:i'),
                'check_out_label' => AttendanceClock::formatWib($checkOut, 'H:i'),
                'check_in_time' => AttendanceClock::formatWib($checkIn, 'H:i'),
                'check_out_time' => AttendanceClock::formatWib($checkOut, 'H:i'),
                'has_pending_correction' => $pending,
                'latest_correction_status' => $latest?->status,
                'latest_correction_status_label' => $latest?->statusLabel(),
                'can_request_correction' => ! $pending,
                'option_label' => $dateLabel.' - '.($record->shift_name ?: '-').' - '.($record->security_post_name ?: '-'),
            ];
        })->values();

        $selectedId = $request->query('record_id');
        $selectedId = is_numeric($selectedId) ? (int) $selectedId : null;
        $selected = $selectedId ? $records->firstWhere('id', $selectedId) : null;

        return Inertia::render('App/Attendance/Correction', array_merge($base, [
            'records' => $records,
            'record' => $selected,
            'selected_record_id' => $selected['id'] ?? null,
        ]));
    }

    public function checkIn(Request $request, int $id, AttendanceActivityService $service): RedirectResponse
    {
        $payload = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0'],
            'captured_at' => ['nullable', 'string'],
        ]);

        $security = Security::query()
            ->where('user_id', $request->user()->id)
            ->first();

        $record = AttendanceRecord::query()->findOrFail($id);

        if ($security === null || (int) $record->security_id !== (int) $security->id) {
            abort(403);
        }

        $service->checkIn($record, $payload);

        return redirect()->back()->with('success', 'Check-in berhasil dicatat.');
    }

    public function checkOut(Request $request, int $id, AttendanceActivityService $service): RedirectResponse
    {
        $payload = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0'],
            'captured_at' => ['nullable', 'string'],
        ]);

        $security = Security::query()
            ->where('user_id', $request->user()->id)
            ->first();

        $record = AttendanceRecord::query()->findOrFail($id);

        if ($security === null || (int) $record->security_id !== (int) $security->id) {
            abort(403);
        }

        $service->checkOut($record, $payload);

        return redirect()->back()->with('success', 'Check-out berhasil dicatat.');
    }

    public function requestCorrection(Request $request, int $id, AttendanceCorrectionService $service): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string'],
            'requested_check_in_at' => ['nullable', 'date_format:H:i'],
            'requested_check_out_at' => ['nullable', 'date_format:H:i'],
            'requested_status' => ['nullable', 'string', 'in:'.implode(',', AttendanceRecord::CORRECTABLE_STATUSES)],
        ]);

        $security = Security::query()
            ->where('user_id', $request->user()->id)
            ->first();

        $record = AttendanceRecord::query()->findOrFail($id);

        if ($security === null || (int) $record->security_id !== (int) $security->id) {
            abort(403);
        }

        $workDate = $record->work_date?->format('Y-m-d');
        if (! $workDate) {
            throw ValidationException::withMessages([
                'correction' => 'Tanggal kerja absensi tidak valid.',
            ]);
        }

        $baseDate = Carbon::parse($workDate, AttendanceClock::OPERATIONAL_TIMEZONE)->startOfDay();

        $requestedCheckIn = null;
        if (! empty($validated['requested_check_in_at'])) {
            $requestedCheckIn = Carbon::parse(
                $baseDate->format('Y-m-d').' '.$validated['requested_check_in_at'],
                AttendanceClock::OPERATIONAL_TIMEZONE
            );
        }

        $requestedCheckOut = null;
        if (! empty($validated['requested_check_out_at'])) {
            $requestedCheckOut = Carbon::parse(
                $baseDate->format('Y-m-d').' '.$validated['requested_check_out_at'],
                AttendanceClock::OPERATIONAL_TIMEZONE
            );

            $isOvernight = ((int) $record->shift_end_day_offset) > 0;
            if (! $isOvernight && $record->shift_start_time && $record->shift_end_time) {
                $isOvernight = substr((string) $record->shift_end_time, 0, 5) < substr((string) $record->shift_start_time, 0, 5);
            }

            $inRef = $requestedCheckIn
                ? $requestedCheckIn->format('H:i')
                : ($record->effectiveCheckInAt() ? AttendanceClock::formatWib($record->effectiveCheckInAt(), 'H:i') : null);
            if (! $inRef && $record->shift_start_time) {
                $inRef = substr((string) $record->shift_start_time, 0, 5);
            }

            if ($isOvernight || ($inRef !== null && $validated['requested_check_out_at'] <= $inRef)) {
                $requestedCheckOut = $requestedCheckOut->addDay();
            }
        }

        $payload = [
            'reason' => $validated['reason'],
            'requested_check_in_at' => $requestedCheckIn,
            'requested_check_out_at' => $requestedCheckOut,
            'requested_status' => $validated['requested_status'] ?? null,
        ];

        $service->request($record, $request->user(), $payload);

        return redirect()->back()->with('success', 'Pengajuan koreksi berhasil dikirim.');
    }

    public function submitCorrection(Request $request, int $id, AttendanceCorrectionService $service): RedirectResponse
    {
        return $this->requestCorrection($request, $id, $service);
    }

    private function applyEffectiveStatusFilter(Builder $query, string $status, Carbon $now): void
    {
        $query->where(function (Builder $query) use ($status, $now) {
            if (in_array($status, AttendanceRecord::CORRECTABLE_STATUSES, true)) {
                $query->where('attendance_records.corrected_status', $status)
                    ->orWhere(function (Builder $query) use ($status, $now) {
                        $query->whereNull('attendance_records.corrected_status');
                        $this->applyStoredStatusFilter($query, $status, $now);
                    });

                return;
            }

            $query->whereNull('attendance_records.corrected_status');
            $this->applyStoredStatusFilter($query, $status, $now);
        });
    }

    private function applyStoredStatusFilter(Builder $query, string $status, Carbon $now): void
    {
        if ($status === AttendanceRecord::STATUS_DAY_OFF) {
            $query->where('attendance_records.shift_type', Shift::TYPE_DAY_OFF);

            return;
        }

        $query->where('attendance_records.shift_type', '!=', Shift::TYPE_DAY_OFF);

        if (in_array($status, AttendanceRecord::CORRECTABLE_STATUSES, true)) {
            $query->where('attendance_records.status', $status);

            return;
        }

        $query->whereIn('attendance_records.status', [
            AttendanceRecord::STATUS_SCHEDULED,
            AttendanceRecord::STATUS_PENDING,
        ]);

        $opensAt = $this->checkInOpensAtExpression($query);
        $nowString = $now->format('Y-m-d H:i:s');

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

    private function checkInOpensAtExpression(Builder $query): string
    {
        if ($query->getConnection()->getDriverName() === 'sqlite') {
            return "datetime(attendance_records.scheduled_start_at, '-' || attendance_records.setting_check_in_open_minutes || ' minutes')";
        }

        return 'DATE_SUB(attendance_records.scheduled_start_at, INTERVAL attendance_records.setting_check_in_open_minutes MINUTE)';
    }
}
