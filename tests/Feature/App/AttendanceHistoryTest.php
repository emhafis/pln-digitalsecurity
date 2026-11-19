<?php

namespace Tests\Feature\App;

use App\Models\AttendanceRecord;
use App\Models\User;
use App\Support\AttendanceClock;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Attendance\AttendanceTestCase;

class AttendanceHistoryTest extends AttendanceTestCase
{
    public function test_security_sees_only_own_history_with_snapshot_data(): void
    {
        $this->makeContext();
        $record = $this->generateForSchedule();

        $otherSecurity = $this->createSecurity($this->unit, 'Satpam Lain');
        $otherMember = $this->period->addMember($otherSecurity);
        $otherSchedule = $this->makeSchedule($this->shift, '2026-11-02', $otherMember, $otherSecurity);
        $this->generate();
        $otherRecord = AttendanceRecord::query()->where('schedule_id', $otherSchedule->id)->firstOrFail();

        $this->actingAs($this->security->user)
            ->get('/app/attendance/history?month=11&year=2026')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('App/Attendance/History')
                ->where('has_security_profile', true)
                ->has('records', 1)
                ->where('records.0.id', $record->id)
                ->where('records.0.post_name', $record->security_post_name)
                ->where('records.0.unit_name', $record->unit_name)
                ->where('records.0.shift_name', $record->shift_name)
                ->missing('records.1')
            );
    }

    public function test_history_defaults_to_current_wib_month_year_and_filters(): void
    {
        $this->makeContext('2026-11-02', '2026-12-05');
        $this->makeSchedule($this->shift, '2026-11-02');
        $this->makeSchedule($this->shift, '2026-12-03');
        $this->generate();

        $nov = AttendanceRecord::query()->whereDate('work_date', '2026-11-02')->where('security_id', $this->security->id)->firstOrFail();
        $dec = AttendanceRecord::query()->whereDate('work_date', '2026-12-03')->where('security_id', $this->security->id)->firstOrFail();

        $this->travelToWib('2026-11-15 08:00:00');
        $this->actingAs($this->security->user)
            ->get('/app/attendance/history')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.month', 11)
                ->where('filters.year', 2026)
                ->has('records', 1)
                ->where('records.0.id', $nov->id)
            );

        $this->actingAs($this->security->user)
            ->get('/app/attendance/history?month=12&year=2026')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.month', 12)
                ->where('filters.year', 2026)
                ->has('records', 1)
                ->where('records.0.id', $dec->id)
            );
    }

    public function test_history_filters_by_effective_status_and_preserves_pagination_filters(): void
    {
        $this->makeContext('2026-11-01', '2026-11-12');
        foreach (range(1, 12) as $day) {
            $this->makeSchedule($this->shift, sprintf('2026-11-%02d', $day));
        }
        $this->generate();

        $absent = AttendanceRecord::query()->whereDate('work_date', '2026-11-05')->where('security_id', $this->security->id)->firstOrFail();
        $absent->forceFill([
            'status' => AttendanceRecord::STATUS_ABSENT,
            'absent_marked_at' => AttendanceClock::wibToUtc('2026-11-05 16:01:00'),
            'snapshot_locked_at' => AttendanceClock::wibToUtc('2026-11-05 16:01:00'),
        ])->save();

        $this->actingAs($this->security->user)
            ->get('/app/attendance/history?month=11&year=2026&status=absent')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', 'absent')
                ->has('records', 1)
                ->where('records.0.id', $absent->id)
                ->where('records.0.status', AttendanceRecord::STATUS_ABSENT)
                ->where('records.0.status_label', AttendanceRecord::statusLabel(AttendanceRecord::STATUS_ABSENT))
            );

        $this->actingAs($this->security->user)
            ->get('/app/attendance/history?month=11&year=2026&page=2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('meta.current_page', 2)
                ->where('meta.total', 12)
                ->where('filters.month', 11)
                ->where('filters.year', 2026)
            );
    }

    public function test_history_shows_effective_wib_times_late_and_duration(): void
    {
        $this->makeContext();
        $record = $this->generateForSchedule();
        $record->forceFill([
            'status' => AttendanceRecord::STATUS_COMPLETED,
            'check_in_at' => AttendanceClock::wibToUtc('2026-11-02 07:10:00'),
            'check_out_at' => AttendanceClock::wibToUtc('2026-11-02 15:00:00'),
            'snapshot_locked_at' => AttendanceClock::wibToUtc('2026-11-02 15:00:00'),
        ])->save();

        $this->actingAs($this->security->user)
            ->get('/app/attendance/history?month=11&year=2026')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('records', 1)
                ->where('records.0.status', AttendanceRecord::STATUS_COMPLETED)
                ->where('records.0.check_in_label', '07:10')
                ->where('records.0.check_out_label', '15:00')
                ->where('records.0.is_late', true)
                ->where('records.0.total_work_label', '7 jam 50 menit')
            );
    }

    public function test_history_empty_state_and_no_security_profile(): void
    {
        $this->makeContext();
        $this->actingAs($this->security->user)
            ->get('/app/attendance/history?month=12&year=2026')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('App/Attendance/History')
                ->where('has_security_profile', true)
                ->has('records', 0)
                ->where('meta.total', 0)
            );

        $user = User::factory()->create(['role' => 'security']);
        $this->actingAs($user)
            ->get('/app/attendance/history')
            ->assertForbidden();
    }

    public function test_opening_history_does_not_change_historical_data(): void
    {
        $this->makeContext();
        $record = $this->generateForSchedule();
        $before = $record->only([
            'status', 'work_date', 'security_name', 'shift_name', 'security_post_name', 'unit_name',
            'scheduled_start_at', 'scheduled_end_at', 'check_in_at', 'check_out_at',
            'snapshot_locked_at', 'last_synced_at', 'setting_gps_radius_meters',
        ]);

        $this->actingAs($this->security->user)
            ->get('/app/attendance/history?month=11&year=2026')
            ->assertOk();

        $record->refresh();
        $after = $record->only(array_keys($before));
        foreach ($before as $key => $value) {
            $a = $value instanceof Carbon ? $value->toDateTimeString() : $value;
            $b = $after[$key] instanceof Carbon ? $after[$key]->toDateTimeString() : $after[$key];
            $this->assertSame($a, $b, "Field {$key} changed");
        }
        $this->assertSame(1, AttendanceRecord::query()->count());
    }
}
