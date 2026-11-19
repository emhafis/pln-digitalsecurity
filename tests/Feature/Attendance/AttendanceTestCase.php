<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSetting;
use App\Models\Schedule;
use App\Models\SchedulePeriod;
use App\Models\SchedulePeriodMember;
use App\Models\Security;
use App\Models\SecurityPost;
use App\Models\SecurityTeam;
use App\Models\Shift;
use App\Models\Unit;
use App\Models\User;
use App\Services\AttendanceGenerator;
use Illuminate\Support\Carbon;
use Tests\Feature\Scheduling\SchedulingTestCase;

abstract class AttendanceTestCase extends SchedulingTestCase
{
    protected Unit $unit;

    protected SecurityPost $post;

    protected SecurityTeam $team;

    protected User $admin;

    protected SchedulePeriod $period;

    protected Security $security;

    protected SchedulePeriodMember $member;

    protected Shift $shift;

    protected Schedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-02 06:50:00', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function travelToWib(string $datetime): void
    {
        Carbon::setTestNow(Carbon::parse($datetime, 'Asia/Jakarta'));
    }

    protected function makeSettings(array $overrides = []): AttendanceSetting
    {
        return AttendanceSetting::create(array_merge(AttendanceSetting::defaults(), $overrides));
    }

    /**
     * @return array{0: Unit, 1: SecurityPost, 2: SecurityTeam, 3: User, 4: SchedulePeriod, 5: Security, 6: SchedulePeriodMember}
     */
    protected function makeContext(
        string $startDate = '2026-11-02',
        string $endDate = '2026-11-02',
        bool $withSettings = true
    ): array {
        $this->unit = $unit = $this->createUnit();
        $this->post = $post = SecurityPost::factory()->create([
            'unit_id' => $unit->id,
            'name' => 'Pos Utama',
            'latitude' => 5.54829000,
            'longitude' => 95.32375000,
        ]);
        $this->team = $team = $this->createTeam($post);
        $this->admin = $admin = $this->createAdmin($unit);
        $this->period = $period = $this->createPeriod(
            $unit,
            $post,
            $team,
            $admin,
            $startDate,
            $endDate,
            SchedulePeriod::STATUS_ACTIVE,
            'Periode Absensi'
        );

        $this->security = $security = $this->createSecurity($unit, 'Satpam Absensi');
        $this->member = $member = $period->addMember($security);

        $this->shift = $this->createShift($unit, Shift::NAME_PAGI, Shift::CODE_BY_NAME[Shift::NAME_PAGI], [
            'start_time' => '07:00:00',
            'end_time' => '15:00:00',
            'end_day_offset' => 0,
        ]);

        if ($withSettings) {
            $this->makeSettings();
        }

        return [$unit, $post, $team, $admin, $period, $security, $member];
    }

    protected function makeSchedule(
        Shift $shift,
        string $date = '2026-11-02',
        ?SchedulePeriodMember $member = null,
        ?Security $security = null
    ): Schedule {
        $member ??= $this->member;
        $security ??= $this->security;

        return Schedule::create([
            'schedule_period_id' => $this->period->id,
            'schedule_period_member_id' => $member->id,
            'security_id' => $security->id,
            'shift_id' => $shift->id,
            'schedule_pattern_id' => null,
            'pattern_day_order' => 1,
            'date' => $date,
            'source' => Schedule::SOURCE_SYSTEM_GENERATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function generate(): array
    {
        return app(AttendanceGenerator::class)->generatePeriod($this->period, $this->admin);
    }

    protected function makeDayOffShift(): Shift
    {
        return $this->createShift($this->unit, Shift::NAME_LEPAS_DINAS, 'L', [
            'type' => Shift::TYPE_DAY_OFF,
            'start_time' => null,
            'end_time' => null,
            'end_day_offset' => 0,
        ]);
    }

    protected function makeRecord(array $overrides = []): AttendanceRecord
    {
        $this->ensureContext();
        $this->schedule = $this->makeSchedule($this->shift);
        $this->generate();
        $record = AttendanceRecord::query()
            ->where('schedule_id', $this->schedule->id)
            ->firstOrFail();

        if ($overrides !== []) {
            $record->forceFill($overrides)->save();
            $record->refresh();
        }

        return $record;
    }

    protected function generateForSchedule(): AttendanceRecord
    {
        $this->ensureContext();
        $this->schedule = $this->makeSchedule($this->shift);
        $this->generate();

        return AttendanceRecord::query()
            ->where('schedule_id', $this->schedule->id)
            ->firstOrFail();
    }

    protected function ensureContext(): void
    {
        if (! isset($this->unit)) {
            $this->makeContext();
        }
    }
}
