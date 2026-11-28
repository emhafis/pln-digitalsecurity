<?php

namespace App\Http\Controllers\App\Concerns;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSetting;
use App\Models\Schedule;
use App\Models\Security;
use App\Models\Shift;
use App\Support\AttendanceClock;
use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

trait BuildsAttendanceContext
{
    /**
     * @return array<string, mixed>
     */
    protected function attendanceContext(Request $request): array
    {
        $user = $request->user();
        $now = AttendanceClock::nowUtc();
        $operationalDate = AttendanceClock::todayWib();

        $security = $user
            ? Security::query()->where('user_id', $user->id)->first()
            : null;

        $context = [
            'security' => $security ? [
                'id' => $security->id,
                'full_name' => $security->full_name,
                'registration_number' => $security->registration_number,
                'photo_url' => PublicStorageUrl::for($security->photo),
            ] : null,
            'operational_date' => [
                'value' => $operationalDate,
                'label' => Carbon::parse($operationalDate, AttendanceClock::OPERATIONAL_TIMEZONE)
                    ->translatedFormat('l, d F Y'),
            ],
            'schedule' => null,
            'shift' => null,
            'post' => null,
            'attendance' => null,
            'windows' => null,
            'state' => 'no_security_profile',
        ];

        if ($security === null) {
            return $context;
        }

        $activeRecord = $this->findActiveAttendanceRecord($security->id, $now);
        $schedule = Schedule::query()
            ->where('security_id', $security->id)
            ->whereDate('date', $operationalDate)
            ->with($this->scheduleRelations())
            ->orderBy('id')
            ->first();

        if ($activeRecord !== null) {
            return $this->contextFromRecord($context, $activeRecord, $now);
        }

        if ($schedule === null) {
            $context['state'] = 'no_schedule';

            return $context;
        }

        $record = $schedule->attendanceRecord;
        $shift = $schedule->shift;
        $period = $schedule->schedulePeriod;
        $post = $period?->securityPost;

        $context['schedule'] = [
            'id' => $schedule->id,
            'date' => $schedule->date?->toDateString(),
            'source' => $schedule->source,
        ];

        $postName = $record?->security_post_name ?? $post?->name;
        $context['post'] = $postName ? [
            'id' => $post?->id ?? $record?->security_post_id,
            'name' => $postName,
            'unit_name' => $record?->unit_name ?? $period?->unit?->unit_name,
            'team_name' => $record?->security_team_name ?? $period?->securityTeam?->name,
        ] : null;

        if ($record !== null) {
            $context['attendance'] = $this->mapAttendance($record, $now);
            $context['windows'] = $this->mapWindowsFromRecord($record);
            $context['state'] = $record->isDayOff() ? 'day_off' : 'record_available';

            $context['shift'] = [
                'id' => $record->shift_id,
                'name' => $record->shift_name,
                'code' => $record->shift_code,
                'type' => $record->shift_type,
                'start_time' => $record->shift_start_time,
                'end_time' => $record->shift_end_time,
                'end_day_offset' => (int) $record->shift_end_day_offset,
                'is_day_off' => $record->isDayOff(),
            ];
        } else {
            $isDayOff = $shift?->isDayOff() ?? false;
            $context['state'] = $isDayOff ? 'day_off' : 'schedule_without_record';
            $context['attendance'] = null;
            $context['windows'] = $this->mapWindowsFromSchedule($schedule, $shift);
            $context['shift'] = $shift ? [
                'id' => $shift->id,
                'name' => $shift->name,
                'code' => $shift->code,
                'type' => $shift->type,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
                'end_day_offset' => (int) $shift->end_day_offset,
                'is_day_off' => $shift->isDayOff(),
            ] : null;
        }

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapAttendance(AttendanceRecord $record, Carbon $now): array
    {
        $checkIn = $record->effectiveCheckInAt();
        $checkOut = $record->effectiveCheckOutAt();
        $status = $record->effectiveStatus($now);

        $totalMinutes = null;
        if ($checkIn && $checkOut) {
            $totalMinutes = $checkIn->diffInMinutes($checkOut);
        }

        $displayStatus = $record->effectiveDisplayStatus($now);

        return [
            'id' => $record->id,
            'schedule_id' => $record->schedule_id,
            'status' => $displayStatus,
            'raw_status' => $status,
            'status_label' => AttendanceRecord::displayStatusLabel($displayStatus),
            'is_late' => $record->isLate(),
            'late_minutes' => $record->lateMinutes(),
            'late_label' => $record->lateLabel(),
            'is_day_off' => $record->isDayOff(),
            'check_in_at' => $checkIn?->toIso8601String(),
            'check_out_at' => $checkOut?->toIso8601String(),
            'check_in_label' => AttendanceClock::formatWib($checkIn, 'H:i'),
            'check_out_label' => AttendanceClock::formatWib($checkOut, 'H:i'),
            'check_in_at_label' => AttendanceClock::formatWib($checkIn),
            'check_out_at_label' => AttendanceClock::formatWib($checkOut),
            'total_work_minutes' => $totalMinutes,
            'total_work_label' => $totalMinutes !== null
                ? sprintf('%d jam %d menit', intdiv($totalMinutes, 60), $totalMinutes % 60)
                : null,
            'work_date' => $record->work_date?->toDateString(),
            'work_date_label' => $record->work_date
                ? Carbon::parse($record->work_date->toDateString(), AttendanceClock::OPERATIONAL_TIMEZONE)
                    ->translatedFormat('l, d F Y')
                : null,
            'has_pending_correction' => $record->hasPendingCorrection(),
            'is_locked' => $record->isLocked(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function mapWindowsFromRecord(AttendanceRecord $record): ?array
    {
        if ($record->scheduled_start_at === null || $record->scheduled_end_at === null) {
            return null;
        }

        $start = $record->scheduled_start_at;
        $end = $record->scheduled_end_at;

        $opensAt = $start->copy()->subMinutes((int) $record->setting_check_in_open_minutes);
        $lateAt = $start->copy()->addMinutes((int) $record->setting_late_tolerance_minutes);
        $checkOutOpensAt = $end->copy()->subMinutes((int) $record->setting_early_checkout_tolerance_minutes);
        $closesAt = $end->copy()->addMinutes((int) $record->setting_checkout_close_minutes);

        return [
            'check_in_open_minutes' => (int) $record->setting_check_in_open_minutes,
            'late_tolerance_minutes' => (int) $record->setting_late_tolerance_minutes,
            'early_checkout_tolerance_minutes' => (int) $record->setting_early_checkout_tolerance_minutes,
            'checkout_close_minutes' => (int) $record->setting_checkout_close_minutes,
            'gps_radius_meters' => (int) $record->setting_gps_radius_meters,
            'max_gps_accuracy_meters' => (int) $record->setting_max_gps_accuracy_meters,
            'scheduled_start_at' => $start->toIso8601String(),
            'scheduled_end_at' => $end->toIso8601String(),
            'check_in_opens_at' => $opensAt->toIso8601String(),
            'late_at' => $lateAt->toIso8601String(),
            'check_out_opens_at' => $checkOutOpensAt->toIso8601String(),
            'check_out_closes_at' => $closesAt->toIso8601String(),
            'scheduled_start_label' => AttendanceClock::formatWib($start),
            'scheduled_end_label' => AttendanceClock::formatWib($end),
            'check_in_opens_label' => AttendanceClock::formatWib($opensAt),
            'late_at_label' => AttendanceClock::formatWib($lateAt),
            'check_out_opens_label' => AttendanceClock::formatWib($checkOutOpensAt),
            'check_out_closes_label' => AttendanceClock::formatWib($closesAt),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function mapWindowsFromSchedule(Schedule $schedule, ?Shift $shift): ?array
    {
        if ($shift === null || $shift->isDayOff()) {
            return null;
        }

        $workDate = $schedule->date?->toDateString();
        if (! $workDate || $shift->start_time === null || $shift->end_time === null) {
            return null;
        }

        $settings = AttendanceSetting::current() ?? AttendanceSetting::defaults();
        $get = fn (string $key) => is_array($settings) ? $settings[$key] : $settings->{$key};

        $start = AttendanceClock::scheduledStart($workDate, (string) $shift->start_time);
        $end = AttendanceClock::scheduledEnd($workDate, (string) $shift->end_time, (int) $shift->end_day_offset);

        $openMin = (int) $get('check_in_open_minutes');
        $lateMin = (int) $get('late_tolerance_minutes');
        $earlyMin = (int) $get('early_checkout_tolerance_minutes');
        $closeMin = (int) $get('checkout_close_minutes');

        $opensAt = $start->copy()->subMinutes($openMin);
        $lateAt = $start->copy()->addMinutes($lateMin);
        $checkOutOpensAt = $end->copy()->subMinutes($earlyMin);
        $closesAt = $end->copy()->addMinutes($closeMin);

        return [
            'check_in_open_minutes' => $openMin,
            'late_tolerance_minutes' => $lateMin,
            'early_checkout_tolerance_minutes' => $earlyMin,
            'checkout_close_minutes' => $closeMin,
            'gps_radius_meters' => (int) $get('gps_radius_meters'),
            'max_gps_accuracy_meters' => (int) $get('max_gps_accuracy_meters'),
            'scheduled_start_at' => $start->toIso8601String(),
            'scheduled_end_at' => $end->toIso8601String(),
            'check_in_opens_at' => $opensAt->toIso8601String(),
            'late_at' => $lateAt->toIso8601String(),
            'check_out_opens_at' => $checkOutOpensAt->toIso8601String(),
            'check_out_closes_at' => $closesAt->toIso8601String(),
            'scheduled_start_label' => AttendanceClock::formatWib($start),
            'scheduled_end_label' => AttendanceClock::formatWib($end),
            'check_in_opens_label' => AttendanceClock::formatWib($opensAt),
            'late_at_label' => AttendanceClock::formatWib($lateAt),
            'check_out_opens_label' => AttendanceClock::formatWib($checkOutOpensAt),
            'check_out_closes_label' => AttendanceClock::formatWib($closesAt),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function contextFromRecord(array $context, AttendanceRecord $record, Carbon $now): array
    {
        $schedule = $record->schedule;
        $period = $schedule?->schedulePeriod;

        $context['schedule'] = [
            'id' => $record->schedule_id,
            'date' => $record->work_date?->toDateString(),
            'source' => $schedule?->source,
        ];

        $context['post'] = $record->security_post_name ? [
            'id' => $record->security_post_id,
            'name' => $record->security_post_name,
            'unit_name' => $record->unit_name ?? $period?->unit?->unit_name,
            'team_name' => $record->security_team_name ?? $period?->securityTeam?->name,
        ] : null;

        $context['attendance'] = $this->mapAttendance($record, $now);
        $context['windows'] = $this->mapWindowsFromRecord($record);
        $context['state'] = $record->isDayOff() ? 'day_off' : 'record_available';
        $context['shift'] = [
            'id' => $record->shift_id,
            'name' => $record->shift_name,
            'code' => $record->shift_code,
            'type' => $record->shift_type,
            'start_time' => $record->shift_start_time,
            'end_time' => $record->shift_end_time,
            'end_day_offset' => (int) $record->shift_end_day_offset,
            'is_day_off' => $record->isDayOff(),
        ];

        return $context;
    }

    /**
     * @return array<int, mixed>
     */
    protected function scheduleRelations(): array
    {
        return [
            'shift:id,name,code,type,start_time,end_time,end_day_offset',
            'schedulePeriod.securityPost:id,name,unit_id,latitude,longitude',
            'schedulePeriod.unit:id,unit_name',
            'schedulePeriod.securityTeam:id,name,code',
            'attendanceRecord' => fn ($query) => $query->with([
                'corrections' => fn ($q) => $q->where('status', AttendanceCorrection::STATUS_PENDING),
            ]),
        ];
    }

    protected function findActiveAttendanceRecord(int $securityId, Carbon $now): ?AttendanceRecord
    {
        $candidates = AttendanceRecord::query()
            ->where('security_id', $securityId)
            ->where('shift_type', Shift::TYPE_WORKING)
            ->where(function ($query) {
                $query->whereNotNull('check_in_at')
                    ->orWhereNotNull('corrected_check_in_at');
            })
            ->where(function ($query) {
                $query->whereNull('check_out_at')
                    ->whereNull('corrected_check_out_at');
            })
            ->whereDate('work_date', '<=', AttendanceClock::todayWib())
            ->with([
                'schedule.shift:id,name,code,type,start_time,end_time,end_day_offset',
                'schedule.schedulePeriod.securityPost:id,name,unit_id,latitude,longitude',
                'schedule.schedulePeriod.unit:id,unit_name',
                'schedule.schedulePeriod.securityTeam:id,name,code',
                'corrections' => fn ($q) => $q->where('status', AttendanceCorrection::STATUS_PENDING),
            ])
            ->orderByDesc('work_date')
            ->orderByDesc('scheduled_start_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        foreach ($candidates as $record) {
            if ($record->isActiveSession($now)) {
                return $record;
            }
        }

        return null;
    }
}
