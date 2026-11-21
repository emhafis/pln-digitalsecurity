<?php

namespace Tests\Feature\Admin;

use App\Models\Apar;
use App\Models\AparInspection;
use App\Models\AttendanceRecord;
use App\Models\GuestVisit;
use App\Models\Hydrant;
use App\Models\HydrantInspection;
use App\Models\PatrolSession;
use App\Models\Schedule;
use App\Models\SchedulePeriod;
use App\Models\SchedulePeriodMember;
use App\Models\Security;
use App\Models\SecurityPost;
use App\Models\SecurityTeam;
use App\Models\Shift;
use App\Models\Unit;
use App\Models\User;
use App\Support\AttendanceClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeUnit(string $name = 'UNIT', ?Unit $parent = null, string $level = 'uid'): Unit
    {
        return Unit::create([
            'unit_name' => $name.' '.Str::upper(Str::random(4)),
            'unit_level' => $level,
            'is_active' => true,
            'parent_id' => $parent?->id,
        ]);
    }

    private function makeUser(string $role, ?Unit $unit = null, array $overrides = []): User
    {
        return User::create(array_merge([
            'username' => $role.Str::lower(Str::random(5)),
            'name' => ucfirst($role).' '.Str::random(3),
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
            'unit_id' => $unit?->id,
        ], $overrides));
    }

    private function makeSecurity(Unit $unit, array $userOverrides = [], array $securityOverrides = []): array
    {
        $user = $this->makeUser('security', $unit, $userOverrides);
        $security = Security::create(array_merge([
            'user_id' => $user->id,
            'full_name' => 'Satpam '.Str::random(4),
            'kta_issued_date' => '2025-01-01',
            'kta_expired_date' => '2027-01-01',
        ], $securityOverrides));

        return [$user, $security];
    }

    private function makePost(Unit $unit, array $overrides = []): SecurityPost
    {
        return SecurityPost::create(array_merge([
            'unit_id' => $unit->id,
            'name' => 'Pos '.Str::random(4),
            'address' => 'Alamat Pos',
            'latitude' => -6.2,
            'longitude' => 106.816,
        ], $overrides));
    }

    private function makeTeam(SecurityPost $post, array $overrides = []): SecurityTeam
    {
        static $seq = 0;
        $names = array_values(SecurityTeam::NAMES);
        $name = $names[$seq % count($names)];
        $seq++;

        return SecurityTeam::create(array_merge([
            'security_post_id' => $post->id,
            'name' => $name,
            'code' => SecurityTeam::codeForName($name),
            'is_active' => true,
        ], $overrides));
    }

    private function makeShift(Unit $unit, array $overrides = []): Shift
    {
        return Shift::create(array_merge([
            'unit_id' => $unit->id,
            'name' => 'Pagi '.Str::random(3),
            'code' => 'P'.Str::upper(Str::random(2)),
            'type' => Shift::TYPE_WORKING,
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'end_day_offset' => 0,
            'is_active' => true,
        ], $overrides));
    }

    private function makePeriod(Unit $unit, SecurityPost $post, SecurityTeam $team, User $creator, array $overrides = []): SchedulePeriod
    {
        return SchedulePeriod::create(array_merge([
            'unit_id' => $unit->id,
            'security_post_id' => $post->id,
            'security_team_id' => $team->id,
            'name' => 'Periode '.Str::random(4),
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'status' => SchedulePeriod::STATUS_ACTIVE,
            'created_by' => $creator->id,
        ], $overrides));
    }

    private function makePeriodMember(SchedulePeriod $period, Security $security): SchedulePeriodMember
    {
        return SchedulePeriodMember::create([
            'schedule_period_id' => $period->id,
            'security_id' => $security->id,
            'schedule_pattern_id' => null,
            'start_offset' => 0,
        ]);
    }

    private function makeSchedule(SchedulePeriod $period, SchedulePeriodMember $member, Security $security, Shift $shift, User $creator, string $date, array $overrides = []): Schedule
    {
        return Schedule::create(array_merge([
            'schedule_period_id' => $period->id,
            'schedule_period_member_id' => $member->id,
            'security_id' => $security->id,
            'shift_id' => $shift->id,
            'schedule_pattern_id' => null,
            'pattern_day_order' => null,
            'date' => $date,
            'source' => Schedule::SOURCE_SYSTEM_GENERATED,
            'notes' => null,
            'created_by' => $creator->id,
            'updated_by' => null,
        ], $overrides));
    }

    private function makeAttendanceRecord(Schedule $schedule, SchedulePeriod $period, Security $security, SecurityPost $post, SecurityTeam $team, Shift $shift, Unit $unit, string $workDate, array $overrides = []): AttendanceRecord
    {
        $start = AttendanceClock::scheduledStart($workDate, '08:00:00');
        $end = AttendanceClock::scheduledEnd($workDate, '16:00:00', 0);

        return AttendanceRecord::create(array_merge([
            'schedule_id' => $schedule->id,
            'schedule_period_id' => $period->id,
            'security_id' => $security->id,
            'security_post_id' => $post->id,
            'shift_id' => $shift->id,
            'work_date' => $workDate,
            'security_name' => $security->full_name,
            'security_registration_number' => $security->registration_number,
            'unit_id' => $unit->id,
            'unit_name' => $unit->unit_name,
            'security_team_id' => $team->id,
            'security_team_name' => $team->name,
            'security_post_name' => $post->name,
            'shift_name' => $shift->name,
            'shift_code' => $shift->code,
            'shift_type' => $shift->type,
            'shift_start_time' => $shift->start_time,
            'shift_end_time' => $shift->end_time,
            'shift_end_day_offset' => $shift->end_day_offset ?? 0,
            'scheduled_start_at' => $start,
            'scheduled_end_at' => $end,
            'post_latitude' => -6.2,
            'post_longitude' => 106.816,
            'setting_gps_radius_meters' => 50,
            'setting_check_in_open_minutes' => 15,
            'setting_late_tolerance_minutes' => 5,
            'setting_early_checkout_tolerance_minutes' => 0,
            'setting_checkout_close_minutes' => 60,
            'setting_max_gps_accuracy_meters' => 50,
            'status' => AttendanceRecord::STATUS_SCHEDULED,
        ], $overrides));
    }

    private function makePatrolSession(SchedulePeriod $period, SecurityTeam $team, SecurityPost $post, Unit $unit, Shift $shift, string $date, string $status, array $overrides = []): PatrolSession
    {
        return PatrolSession::create(array_merge([
            'schedule_period_id' => $period->id,
            'security_team_id' => $team->id,
            'security_post_id' => $post->id,
            'unit_id' => $unit->id,
            'shift_id' => $shift->id,
            'work_date' => $date,
            'team_name' => $team->name,
            'team_code' => $team->code,
            'post_name' => $post->name,
            'post_code' => $post->post_code,
            'unit_name' => $unit->unit_name,
            'unit_code' => $unit->unit_code,
            'shift_name' => $shift->name,
            'shift_code' => $shift->code,
            'shift_type' => $shift->type,
            'shift_start_time' => $shift->start_time,
            'shift_end_time' => $shift->end_time,
            'shift_end_day_offset' => $shift->end_day_offset ?? 0,
            'status' => $status,
            'started_at' => null,
            'ended_at' => null,
        ], $overrides));
    }

    public function test_guest_is_redirected_from_admin_dashboard(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect('/login');
    }

    public function test_security_cannot_access_admin_dashboard(): void
    {
        $unit = $this->makeUnit('U');
        [$user] = $this->makeSecurity($unit);

        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_admin_can_access_dashboard(): void
    {
        $unit = $this->makeUnit('U');
        $admin = $this->makeUser('admin', $unit);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->has('filters')
                ->has('context')
                ->has('securityPosts')
                ->has('personnel')
                ->has('patrol')
                ->has('attendance')
                ->has('schedule')
                ->has('assets')
                ->has('guestBook')
                ->has('activities')
            );
    }

    public function test_superadmin_can_access_dashboard(): void
    {
        $sa = $this->makeUser('superadmin');

        $this->actingAs($sa)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_admin_only_sees_own_unit_and_descendants_and_superadmin_sees_all(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-16 03:00:00', 'UTC')); // 10:00 WIB

        $parent = $this->makeUnit('PARENT');
        $child = $this->makeUnit('CHILD', $parent, 'up');
        $other = $this->makeUnit('OTHER');

        $admin = $this->makeUser('admin', $parent);
        $creator = $admin;
        $sa = $this->makeUser('superadmin');

        $postA = $this->makePost($child, ['name' => 'Pos Child']);
        $postB = $this->makePost($other, ['name' => 'Pos Other']);

        // personnel (securities) in each branch
        [$uSecA, $secA] = $this->makeSecurity($child);
        [$uSecB, $secB] = $this->makeSecurity($other);

        $teamA = $this->makeTeam($postA);
        $teamB = $this->makeTeam($postB);
        $shiftA = $this->makeShift($child, ['name' => 'Shift A', 'code' => 'SA']);
        $shiftB = $this->makeShift($other, ['name' => 'Shift B', 'code' => 'SB']);

        $periodA = $this->makePeriod($child, $postA, $teamA, $creator);
        $periodB = $this->makePeriod($other, $postB, $teamB, $sa);
        $pmA = $this->makePeriodMember($periodA, $secA);
        $pmB = $this->makePeriodMember($periodB, $secB);

        $today = '2026-11-16';
        $schA = $this->makeSchedule($periodA, $pmA, $secA, $shiftA, $creator, $today);
        $schB = $this->makeSchedule($periodB, $pmB, $secB, $shiftB, $sa, $today);

        $this->makePatrolSession($periodA, $teamA, $postA, $child, $shiftA, $today, PatrolSession::STATUS_COMPLETED);
        $this->makePatrolSession($periodB, $teamB, $postB, $other, $shiftB, $today, PatrolSession::STATUS_COMPLETED);

        $this->makeAttendanceRecord($schA, $periodA, $secA, $postA, $teamA, $shiftA, $child, $today);
        $this->makeAttendanceRecord($schB, $periodB, $secB, $postB, $teamB, $shiftB, $other, $today);

        $aparA = Apar::create([
            'unit_id' => $child->id, 'apar_code' => 'APAR-A1', 'apar_type' => 'dry_chemical',
            'brand' => 'B', 'capacity' => '3kg', 'placement' => 'X', 'qr_code' => (string) Str::uuid(), 'is_active' => true,
        ]);
        $aparB = Apar::create([
            'unit_id' => $other->id, 'apar_code' => 'APAR-B1', 'apar_type' => 'co2',
            'brand' => 'B', 'capacity' => '3kg', 'placement' => 'Y', 'qr_code' => (string) Str::uuid(), 'is_active' => true,
        ]);

        GuestVisit::create([
            'unit_id' => $child->id, 'guest_book_qr_code' => $child->guest_book_qr_code,
            'full_name' => 'Tamu Child', 'phone_number' => '081234567890', 'company_name' => 'C',
            'position' => 'S', 'visit_purpose' => 'R', 'person_to_meet' => 'P',
            'check_in_at' => '2026-11-16 01:00:00', 'consent_given' => true, 'consent_at' => '2026-11-16 01:00:00',
            'unit_name_snapshot' => $child->unit_name, 'unit_code_snapshot' => $child->unit_code,
            'selfie_path' => 'guest-book/selfie/secret.jpg', 'signature_path' => 'guest-book/signature/secret.png',
        ]);
        GuestVisit::create([
            'unit_id' => $other->id, 'guest_book_qr_code' => $other->guest_book_qr_code,
            'full_name' => 'Tamu Other', 'phone_number' => '089999999999', 'company_name' => 'D',
            'position' => 'S', 'visit_purpose' => 'R', 'person_to_meet' => 'P',
            'check_in_at' => '2026-11-16 01:00:00', 'consent_given' => true, 'consent_at' => '2026-11-16 01:00:00',
            'unit_name_snapshot' => $other->unit_name, 'unit_code_snapshot' => $other->unit_code,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('personnel.guards_total', 1)
                ->where('personnel.posts_total', 1)
                ->where('personnel.teams_total', 1)
                ->where('patrol.today.total', 1)
                ->where('patrol.today.completed', 1)
                ->where('attendance.today.total', 1)
                ->where('schedule.today.total', 1)
                ->where('assets.apar.total', 1)
                ->where('guestBook.today.total', 1)
                ->has('securityPosts', 1)
                ->where('securityPosts.0.name', 'Pos Child')
            );

        $this->actingAs($sa)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('personnel.guards_total', 2)
                ->where('personnel.posts_total', 2)
                ->where('personnel.teams_total', 2)
                ->where('patrol.today.total', 2)
                ->where('patrol.today.completed', 2)
                ->where('attendance.today.total', 2)
                ->where('schedule.today.total', 2)
                ->where('assets.apar.total', 2)
                ->where('guestBook.today.total', 2)
                ->has('securityPosts', 2)
            );
    }

    public function test_guest_book_payload_does_not_expose_sensitive_fields(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-16 03:00:00', 'UTC'));
        $unit = $this->makeUnit('U');
        $admin = $this->makeUser('admin', $unit);

        GuestVisit::create([
            'unit_id' => $unit->id, 'guest_book_qr_code' => $unit->guest_book_qr_code,
            'full_name' => 'Tamu Sensitif', 'phone_number' => '081234567890', 'company_name' => 'Corp',
            'position' => 'Mgr', 'visit_purpose' => 'Meeting', 'person_to_meet' => 'Boss',
            'check_in_at' => '2026-11-16 02:00:00', 'consent_given' => true, 'consent_at' => '2026-11-16 02:00:00',
            'unit_name_snapshot' => $unit->unit_name, 'unit_code_snapshot' => $unit->unit_code,
            'selfie_path' => 'guest-book/selfie/private.jpg',
            'signature_path' => 'guest-book/signature/private.png',
            'ip_address' => '10.0.0.1', 'user_agent' => 'SecretAgent',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $page = $response->original->getData()['page'];
        $propsJson = json_encode($page['props']);

        $this->assertStringNotContainsString('081234567890', $propsJson);
        $this->assertStringNotContainsString('selfie_path', $propsJson);
        $this->assertStringNotContainsString('signature_path', $propsJson);
        $this->assertStringNotContainsString('private.jpg', $propsJson);
        $this->assertStringNotContainsString('private.png', $propsJson);
        $this->assertStringNotContainsString('ip_address', $propsJson);
        $this->assertStringNotContainsString('user_agent', $propsJson);
        $this->assertStringNotContainsString('SecretAgent', $propsJson);

        $response->assertInertia(fn (Assert $page) => $page
            ->has('guestBook.recent', 1)
            ->where('guestBook.recent.0.full_name', 'Tamu Sensitif')
            ->missing('guestBook.recent.0.phone_number')
            ->missing('guestBook.recent.0.selfie_path')
            ->missing('guestBook.recent.0.signature_path')
        );
    }

    public function test_period_filter_is_validated_and_affects_activities_range(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-16 03:00:00', 'UTC')); // today WIB 2026-11-16
        $unit = $this->makeUnit('U');
        $admin = $this->makeUser('admin', $unit);
        $post = $this->makePost($unit);
        $team = $this->makeTeam($post);
        $shift = $this->makeShift($unit, ['name' => 'Shift R', 'code' => 'SR']);
        $period = $this->makePeriod($unit, $post, $team, $admin);

        // session in range for 7d (today)
        $this->makePatrolSession($period, $team, $post, $unit, $shift, '2026-11-16', PatrolSession::STATUS_COMPLETED, [
            'started_at' => '2026-11-16 01:00:00',
            'ended_at' => '2026-11-16 02:00:00',
        ]);
        // session outside 7d but inside 30d
        $this->makePatrolSession($period, $team, $post, $unit, $shift, '2026-10-25', PatrolSession::STATUS_COMPLETED, [
            'started_at' => '2026-10-25 01:00:00',
            'ended_at' => '2026-10-25 02:00:00',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['period' => '7d']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.period', '7d')
                ->where('filters.period_label', '7 hari terakhir')
                ->where('patrol.today.total', 1) // today stats remain factual for today
                ->has('activities', 1)
            );

        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['period' => '30d']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.period', '30d')
                ->has('activities', 2)
            );

        // invalid period falls back to today
        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['period' => 'invalid']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('filters.period', 'today'));
    }

    public function test_dashboard_uses_real_kpis_for_assets_attendance_and_schedule(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-16 03:00:00', 'UTC')); // 10:00 WIB
        $unit = $this->makeUnit('U');
        $admin = $this->makeUser('admin', $unit);
        $post = $this->makePost($unit);
        $team = $this->makeTeam($post);
        $shift = $this->makeShift($unit, ['name' => 'Pagi', 'code' => 'PAG']);
        $period = $this->makePeriod($unit, $post, $team, $admin);
        [$u1, $s1] = $this->makeSecurity($unit, [], ['full_name' => 'Satpam One']);
        [$u2, $s2] = $this->makeSecurity($unit, [], ['full_name' => 'Satpam Two']);
        $pm1 = $this->makePeriodMember($period, $s1);
        $pm2 = $this->makePeriodMember($period, $s2);
        $today = '2026-11-16';

        $sch1 = $this->makeSchedule($period, $pm1, $s1, $shift, $admin, $today);
        $sch2 = $this->makeSchedule($period, $pm2, $s2, $shift, $admin, $today);

        // record 1: checked in and completed
        $this->makeAttendanceRecord($sch1, $period, $s1, $post, $team, $shift, $unit, $today, [
            'status' => AttendanceRecord::STATUS_COMPLETED,
            'check_in_at' => AttendanceClock::scheduledStart($today, '08:00:00')->copy()->addMinutes(2),
            'check_in_captured_at' => AttendanceClock::scheduledStart($today, '08:00:00')->copy()->addMinutes(2),
            'check_out_at' => AttendanceClock::scheduledEnd($today, '16:00:00', 0),
            'check_out_captured_at' => AttendanceClock::scheduledEnd($today, '16:00:00', 0),
        ]);
        // record 2: still scheduled (no check-in) -> must not be treated as mangkir by absence-from-missing-record
        $this->makeAttendanceRecord($sch2, $period, $s2, $post, $team, $shift, $unit, $today, [
            'status' => AttendanceRecord::STATUS_SCHEDULED,
        ]);

        $apar1 = Apar::create([
            'unit_id' => $unit->id, 'apar_code' => 'APAR-0001', 'apar_type' => 'dry_chemical',
            'brand' => 'X', 'capacity' => '6kg', 'placement' => 'Lobi', 'qr_code' => (string) Str::uuid(), 'is_active' => true,
        ]);
        $apar2 = Apar::create([
            'unit_id' => $unit->id, 'apar_code' => 'APAR-0002', 'apar_type' => 'co2',
            'brand' => 'Y', 'capacity' => '3kg', 'placement' => 'Gudang', 'qr_code' => (string) Str::uuid(), 'is_active' => true,
        ]);
        AparInspection::create([
            'apar_id' => $apar1->id, 'unit_id' => $unit->id, 'security_id' => $s1->id,
            'inspected_at' => '2026-11-15 02:00:00', 'result' => 'not_ok',
            'unit_name_snapshot' => $unit->unit_name, 'unit_code_snapshot' => $unit->unit_code,
            'apar_code_snapshot' => $apar1->apar_code, 'apar_type_snapshot' => $apar1->apar_type,
        ]);
        AparInspection::create([
            'apar_id' => $apar2->id, 'unit_id' => $unit->id, 'security_id' => $s1->id,
            'inspected_at' => '2026-11-15 02:00:00', 'result' => 'ok',
            'unit_name_snapshot' => $unit->unit_name, 'unit_code_snapshot' => $unit->unit_code,
            'apar_code_snapshot' => $apar2->apar_code, 'apar_type_snapshot' => $apar2->apar_type,
        ]);

        $hyd1 = Hydrant::create([
            'unit_id' => $unit->id, 'hydrant_code' => 'HYD-0001', 'name' => 'Pump 1',
            'hydrant_type' => 'fire_pump_diesel', 'brand' => 'H', 'model' => 'M',
            'placement' => 'Outdoor', 'qr_code' => (string) Str::uuid(), 'is_active' => true,
        ]);
        HydrantInspection::create([
            'hydrant_id' => $hyd1->id, 'unit_id' => $unit->id, 'security_id' => $s1->id,
            'inspected_at' => '2026-11-14 02:00:00', 'result' => 'ok',
            'unit_name_snapshot' => $unit->unit_name, 'unit_code_snapshot' => $unit->unit_code,
            'hydrant_code_snapshot' => $hyd1->hydrant_code, 'name_snapshot' => $hyd1->name,
            'hydrant_type_snapshot' => $hyd1->hydrant_type,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('attendance.today.total', 2)
                ->where('attendance.today.completed', 1)
                ->where('attendance.today.checked_in', 1)
                ->where('attendance.today.absent', 0)
                ->where('schedule.today.total', 2)
                ->where('assets.apar.total', 2)
                ->where('assets.apar.last_not_ok', 1)
                ->where('assets.hydrant.total', 1)
                ->where('assets.hydrant.last_not_ok', 0)
                ->has('activities')
            );
    }
}
