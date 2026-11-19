<?php

namespace Tests\Feature\Admin;

use App\Models\GuestVisit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuestBookAdminTest extends TestCase
{
    use RefreshDatabase;

    private function makeUnit(string $name = 'UNIT', ?Unit $parent = null): Unit
    {
        return Unit::create([
            'unit_name' => $name.' '.Str::upper(Str::random(3)),
            'unit_level' => $parent ? 'up' : 'uid',
            'is_active' => true,
            'parent_id' => $parent?->id,
        ]);
    }

    private function makeAdmin(Unit $unit): User
    {
        return User::create([
            'username' => 'admin'.Str::random(4),
            'name' => 'Admin Test',
            'password' => 'pass',
            'role' => 'admin',
            'is_active' => true,
            'unit_id' => $unit->id,
        ]);
    }

    private function makeSuperadmin(): User
    {
        return User::create([
            'username' => 'sa'.Str::random(4),
            'name' => 'Super Admin',
            'password' => 'pass',
            'role' => 'superadmin',
            'is_active' => true,
        ]);
    }

    private function makeVisit(Unit $unit, array $overrides = []): GuestVisit
    {
        $unit = $unit->fresh();
        return GuestVisit::create(array_merge([
            'unit_id' => $unit->id,
            'guest_book_qr_code' => $unit->guest_book_qr_code,
            'full_name' => 'Tamu '.Str::random(3),
            'phone_number' => '08123456789',
            'company_name' => 'PT X',
            'position' => 'Staff',
            'visit_purpose' => 'Rapat',
            'person_to_meet' => 'Andi',
            'check_in_at' => now()->subHour(),
            'consent_given' => true,
            'consent_at' => now()->subHour(),
            'unit_name_snapshot' => $unit->unit_name,
            'unit_code_snapshot' => $unit->unit_code,
        ], $overrides));
    }

    public function test_superadmin_sees_all_units_visits(): void
    {
        $u1 = $this->makeUnit('A');
        $u2 = $this->makeUnit('B');
        $v1 = $this->makeVisit($u1, ['full_name' => 'Alpha']);
        $v2 = $this->makeVisit($u2, ['full_name' => 'Beta']);
        $sa = $this->makeSuperadmin();

        $resp = $this->actingAs($sa)->get('/admin/guest-book');
        $resp->assertOk();
        $resp->assertSee('Alpha', false);
        $resp->assertSee('Beta', false);
    }

    public function test_admin_sees_own_and_descendant_units_only(): void
    {
        $parent = $this->makeUnit('PARENT');
        $child = $this->makeUnit('CHILD', $parent);
        $other = $this->makeUnit('OTHER');
        $admin = $this->makeAdmin($parent);
        $vChild = $this->makeVisit($child, ['full_name' => 'ChildGuest']);
        $vOther = $this->makeVisit($other, ['full_name' => 'OtherGuest']);

        $resp = $this->actingAs($admin)->get('/admin/guest-book');
        $resp->assertOk();
        $resp->assertSee('ChildGuest', false);
        $resp->assertDontSee('OtherGuest', false);
    }

    public function test_admin_cannot_view_detail_of_other_unit(): void
    {
        $parent = $this->makeUnit('P');
        $other = $this->makeUnit('O');
        $admin = $this->makeAdmin($parent);
        $visit = $this->makeVisit($other);

        $this->actingAs($admin)->get("/admin/guest-book/{$visit->id}")->assertStatus(403);
    }

    public function test_dashboard_counts_correct(): void
    {
        $this->travelTo('2026-11-17 10:00:00');
        $unit = $this->makeUnit('U');
        $sa = $this->makeSuperadmin();
        $this->makeVisit($unit, ['check_in_at' => now()->subHour(), 'check_out_at' => null]); // active, today
        $this->makeVisit($unit, ['check_in_at' => now()->subHours(2), 'check_out_at' => now()->subMinutes(10)]); // checked out today
        $this->makeVisit($unit, ['check_in_at' => now()->subDay(), 'check_out_at' => null]); // active, not today

        $resp = $this->actingAs($sa)->get('/admin/guest-book');
        $resp->assertOk();
        $stats = $resp->viewData('page')['props']['stats'] ?? null;
        // Inertia response: props in page
    }

    public function test_dashboard_counts_and_status_via_props(): void
    {
        $this->travelTo('2026-11-17 10:00:00');
        $unit = $this->makeUnit('U');
        $sa = $this->makeSuperadmin();
        $vActive = $this->makeVisit($unit, ['full_name' => 'ActiveOne', 'check_in_at' => now()->subHour(), 'check_out_at' => null]);
        $vOut = $this->makeVisit($unit, ['full_name' => 'OutOne', 'check_in_at' => now()->subHours(2), 'check_out_at' => now()->subMinutes(10)]);
        $vOld = $this->makeVisit($unit, ['check_in_at' => now()->subDay(), 'check_out_at' => null]);

        $resp = $this->actingAs($sa)->get('/admin/guest-book');
        $resp->assertOk();
        $page = $resp->original->getData()['page'];
        $props = $page['props'];
        $this->assertSame(2, $props['stats']['active']);
        $this->assertSame(2, $props['stats']['today']);
        $this->assertSame(1, $props['stats']['checked_out_today']);
        $items = collect($props['items']);
        $this->assertSame('active', $items->firstWhere('id', $vActive->id)['status']);
        $this->assertSame('checked_out', $items->firstWhere('id', $vOut->id)['status']);
    }

    public function test_filter_search_does_not_bypass_scope(): void
    {
        $parent = $this->makeUnit('P');
        $other = $this->makeUnit('O');
        $admin = $this->makeAdmin($parent);
        $this->makeVisit($parent, ['full_name' => 'Scoped']);
        $this->makeVisit($other, ['full_name' => 'ScopedOther']);

        $resp = $this->actingAs($admin)->get('/admin/guest-book?search=Scoped');
        $resp->assertOk();
        $resp->assertSee('Scoped', false);
        $resp->assertDontSee('ScopedOther', false);
    }

    public function test_detail_shows_snapshot(): void
    {
        $unit = $this->makeUnit('SNAP');
        $sa = $this->makeSuperadmin();
        $visit = $this->makeVisit($unit, ['unit_name_snapshot' => 'SNAPNAME', 'unit_code_snapshot' => 'U9999']);

        $resp = $this->actingAs($sa)->get("/admin/guest-book/{$visit->id}");
        $resp->assertOk();
        $resp->assertSee('SNAPNAME', false);
        $resp->assertSee('U9999', false);
    }

    public function test_private_file_requires_authorization_and_serves(): void
    {
        Storage::fake('local');
        $unit = $this->makeUnit('U');
        $other = $this->makeUnit('O');
        $admin = $this->makeAdmin($other);
        $sa = $this->makeSuperadmin();
        $path = 'guest-book/selfie/'.$unit->id.'/test.jpg';
        Storage::disk('local')->put($path, 'imgdata');
        $visit = $this->makeVisit($unit, ['selfie_path' => $path]);

        $this->actingAs($admin)->get("/admin/guest-book/{$visit->id}/file/selfie")->assertStatus(403);
        $this->actingAs($sa)->get("/admin/guest-book/{$visit->id}/file/selfie")->assertOk();
    }

    public function test_admin_manual_checkout_success(): void
    {
        $this->travelTo('2026-11-17 12:00:00');
        $unit = $this->makeUnit('U');
        $admin = $this->makeAdmin($unit);
        $visit = $this->makeVisit($unit, ['check_in_at' => now()->subHour()]);

        $resp = $this->actingAs($admin)->post("/admin/guest-book/{$visit->id}/checkout");
        $resp->assertRedirect();
        $visit->refresh();
        $this->assertEquals('2026-11-17 12:00:00', $visit->check_out_at->format('Y-m-d H:i:s'));
        $this->assertSame('admin', $visit->checkout_source);
        $this->assertSame($admin->id, $visit->checked_out_by);
        $this->assertEquals('2026-11-17 11:00:00', $visit->check_in_at->format('Y-m-d H:i:s'));
    }

    public function test_manual_checkout_cannot_be_done_twice_or_overwrite(): void
    {
        $this->travelTo('2026-11-17 12:00:00');
        $unit = $this->makeUnit('U');
        $admin = $this->makeAdmin($unit);
        $visit = $this->makeVisit($unit);
        $this->actingAs($admin)->post("/admin/guest-book/{$visit->id}/checkout");
        $first = $visit->fresh()->check_out_at->format('Y-m-d H:i:s');

        $this->travelTo('2026-11-17 13:00:00');
        $this->actingAs($admin)->post("/admin/guest-book/{$visit->id}/checkout")->assertRedirect();
        $this->assertEquals($first, $visit->fresh()->check_out_at->format('Y-m-d H:i:s'));
    }

    public function test_admin_cannot_checkout_other_unit(): void
    {
        $parent = $this->makeUnit('P');
        $other = $this->makeUnit('O');
        $admin = $this->makeAdmin($parent);
        $visit = $this->makeVisit($other);

        $this->actingAs($admin)->post("/admin/guest-book/{$visit->id}/checkout")->assertStatus(403);
        $this->assertNull($visit->fresh()->check_out_at);
    }

    public function test_checkin_history_unchanged_after_manual_checkout(): void
    {
        $unit = $this->makeUnit('U');
        $admin = $this->makeAdmin($unit);
        $visit = $this->makeVisit($unit, [
            'full_name' => 'History',
            'company_name' => 'PT Hist',
            'visit_purpose' => 'Tujuan',
            'person_to_meet' => 'X',
            'guest_book_qr_code' => $unit->fresh()->guest_book_qr_code,
        ]);
        $before = $visit->only(['full_name','company_name','visit_purpose','person_to_meet','guest_book_qr_code','unit_id','unit_name_snapshot','unit_code_snapshot','phone_number','check_in_at']);

        $this->actingAs($admin)->post("/admin/guest-book/{$visit->id}/checkout");
        $visit->refresh();
        $this->assertSame($before['full_name'], $visit->full_name);
        $this->assertSame($before['company_name'], $visit->company_name);
        $this->assertSame($before['visit_purpose'], $visit->visit_purpose);
        $this->assertSame($before['person_to_meet'], $visit->person_to_meet);
        $this->assertSame($before['guest_book_qr_code'], $visit->guest_book_qr_code);
        $this->assertSame($before['unit_id'], $visit->unit_id);
        $this->assertSame($before['unit_name_snapshot'], $visit->unit_name_snapshot);
        $this->assertSame($before['unit_code_snapshot'], $visit->unit_code_snapshot);
        $this->assertSame($before['phone_number'], $visit->phone_number);
        $this->assertEquals($before['check_in_at']->format('Y-m-d H:i:s'), $visit->check_in_at->format('Y-m-d H:i:s'));
    }
}
