<?php

namespace Tests\Feature\Admin;

use App\Models\GuestVisit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuestBookQrTest extends TestCase
{
    use RefreshDatabase;

    private function makeUnit(string $name = 'UNIT', array $overrides = []): Unit
    {
        return Unit::create(array_merge([
            'unit_name' => $name.' '.Str::upper(Str::random(3)),
            'unit_level' => 'uid',
            'is_active' => true,
        ], $overrides));
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

    // 1. Superadmin dapat melihat daftar QR unit.
    public function test_superadmin_can_view_qr_list(): void
    {
        $sa = $this->makeSuperadmin();
        $unit = $this->makeUnit('LIST');
        $resp = $this->actingAs($sa)->get('/admin/guest-book/qr');
        $resp->assertOk();
        $resp->assertSee($unit->unit_name, false);
    }

    // 2. Admin dapat melihat/unduh QR unit sendiri (scoped), tidak bisa kelola/regenerasi/backfill, dan tidak bisa akses unit lain.
    public function test_admin_can_view_scoped_qr_but_cannot_manage_or_access_others(): void
    {
        $unit = $this->makeUnit('A');
        $other = $this->makeUnit('B');
        $admin = $this->makeAdmin($unit);
        $unit->guest_book_checkout_qr_code = (string) Str::uuid();
        $unit->save();

        $this->actingAs($admin)->get('/admin/guest-book/qr')->assertOk()->assertSee($unit->unit_name, false)->assertDontSee($other->unit_name, false);
        $this->actingAs($admin)->get("/admin/guest-book/qr/{$unit->id}")->assertOk();
        $respIn = $this->actingAs($admin)->get("/admin/guest-book/qr/{$unit->id}/download/checkin");
        $respIn->assertOk();
        $this->assertStringStartsWith("\x89PNG", $respIn->getContent());

        $this->actingAs($admin)->get("/admin/guest-book/qr/{$other->id}")->assertStatus(403);
        $this->actingAs($admin)->get("/admin/guest-book/qr/{$other->id}/download/checkin")->assertStatus(403);

        $this->actingAs($admin)->post("/admin/guest-book/qr/{$unit->id}/regenerate-checkin")->assertStatus(403);
        $this->actingAs($admin)->post("/admin/guest-book/qr/{$unit->id}/regenerate-checkout")->assertStatus(403);
        $this->actingAs($admin)->post('/admin/guest-book/qr/backfill')->assertStatus(403);
    }

    // 3. QR Check-in dan Check-out ditampilkan dengan URL yang benar.
    public function test_qr_urls_are_correct(): void
    {
        $sa = $this->makeSuperadmin();
        $unit = $this->makeUnit('URL');
        $unit->guest_book_checkout_qr_code = (string) Str::uuid();
        $unit->save();

        $resp = $this->actingAs($sa)->get("/admin/guest-book/qr/{$unit->id}");
        $resp->assertOk();
        $page = $resp->original->getData()['page'];
        $props = $page['props'];
        $this->assertSame(url('/guest-book/'.$unit->fresh()->guest_book_qr_code), $props['checkin']['url']);
        $this->assertSame(url('/guest-book/checkout/'.$unit->fresh()->guest_book_checkout_qr_code), $props['checkout']['url']);
    }

    // 4. Unduhan QR menghasilkan PNG valid.
    public function test_download_produces_valid_png(): void
    {
        $sa = $this->makeSuperadmin();
        $unit = $this->makeUnit('DL');
        $unit->guest_book_checkout_qr_code = (string) Str::uuid();
        $unit->save();

        $respIn = $this->actingAs($sa)->get("/admin/guest-book/qr/{$unit->id}/download/checkin");
        $respIn->assertOk();
        $this->assertStringContainsString('image/png', $respIn->headers->get('Content-Type'));
        $this->assertStringStartsWith("\x89PNG", $respIn->getContent());

        $respOut = $this->actingAs($sa)->get("/admin/guest-book/qr/{$unit->id}/download/checkout");
        $respOut->assertOk();
        $this->assertStringContainsString('image/png', $respOut->headers->get('Content-Type'));
        $this->assertStringStartsWith("\x89PNG", $respOut->getContent());
    }

    // 5. Regenerasi Check-in tidak mengubah QR Check-out.
    public function test_regenerate_checkin_does_not_change_checkout(): void
    {
        $sa = $this->makeSuperadmin();
        $unit = $this->makeUnit('RCI');
        $unit->guest_book_checkout_qr_code = (string) Str::uuid();
        $unit->save();
        $oldIn = $unit->fresh()->guest_book_qr_code;
        $oldOut = $unit->fresh()->guest_book_checkout_qr_code;

        $this->actingAs($sa)->post("/admin/guest-book/qr/{$unit->id}/regenerate-checkin")->assertRedirect();
        $unit->refresh();
        $this->assertNotSame($oldIn, $unit->guest_book_qr_code);
        $this->assertSame($oldOut, $unit->guest_book_checkout_qr_code);
    }

    // 6. Regenerasi Check-out tidak mengubah QR Check-in.
    public function test_regenerate_checkout_does_not_change_checkin(): void
    {
        $sa = $this->makeSuperadmin();
        $unit = $this->makeUnit('RCO');
        $unit->guest_book_checkout_qr_code = (string) Str::uuid();
        $unit->save();
        $oldIn = $unit->fresh()->guest_book_qr_code;
        $oldOut = $unit->fresh()->guest_book_checkout_qr_code;

        $this->actingAs($sa)->post("/admin/guest-book/qr/{$unit->id}/regenerate-checkout")->assertRedirect();
        $unit->refresh();
        $this->assertSame($oldIn, $unit->guest_book_qr_code);
        $this->assertNotSame($oldOut, $unit->guest_book_checkout_qr_code);
    }

    // 7. QR lama ditolak setelah regenerasi.
    public function test_old_qr_rejected_after_regeneration(): void
    {
        Storage::fake('local');
        $sa = $this->makeSuperadmin();
        $unit = $this->makeUnit('OLD');
        $unit->guest_book_checkout_qr_code = (string) Str::uuid();
        $unit->save();
        $oldIn = $unit->fresh()->guest_book_qr_code;
        $oldOut = $unit->fresh()->guest_book_checkout_qr_code;

        $this->actingAs($sa)->post("/admin/guest-book/qr/{$unit->id}/regenerate-checkin");
        $respIn = $this->get('/guest-book/'.$oldIn);
        $respIn->assertStatus(404);
        $this->assertTrue(
            str_contains($respIn->getContent(), 'Kode QR tidak valid') || str_contains($respIn->getContent(), 'QR Code tidak valid'),
            'Expected old check-in QR to be rejected as invalid.'
        );

        $this->actingAs($sa)->post("/admin/guest-book/qr/{$unit->id}/regenerate-checkout");
        $respOut = $this->get('/guest-book/checkout/'.$oldOut);
        $respOut->assertStatus(404);
        $this->assertTrue(
            str_contains($respOut->getContent(), 'Kode QR tidak valid') || str_contains($respOut->getContent(), 'QR Code tidak valid'),
            'Expected old check-out QR to be rejected as invalid.'
        );
    }

    // 8. Histori kunjungan tidak berubah setelah regenerasi.
    public function test_visit_history_unchanged_after_regeneration(): void
    {
        $sa = $this->makeSuperadmin();
        $unit = $this->makeUnit('HIST');
        $unit->guest_book_checkout_qr_code = (string) Str::uuid();
        $unit->save();
        $visit = $this->makeVisit($unit);
        $fresh = $visit->fresh();
        $before = $fresh->only(['guest_book_qr_code','unit_name_snapshot','unit_code_snapshot','unit_id','full_name','phone_number','company_name','check_out_at','checkout_source','checked_out_by']);
        $beforeCheckIn = $fresh->check_in_at->format('Y-m-d H:i:s');

        $this->actingAs($sa)->post("/admin/guest-book/qr/{$unit->id}/regenerate-checkin");
        $this->actingAs($sa)->post("/admin/guest-book/qr/{$unit->id}/regenerate-checkout");

        $after = $visit->fresh();
        $this->assertSame($before['guest_book_qr_code'], $after->guest_book_qr_code);
        $this->assertSame($before['unit_name_snapshot'], $after->unit_name_snapshot);
        $this->assertSame($before['unit_code_snapshot'], $after->unit_code_snapshot);
        $this->assertSame($before['unit_id'], $after->unit_id);
        $this->assertSame($before['full_name'], $after->full_name);
        $this->assertSame($before['phone_number'], $after->phone_number);
        $this->assertSame($before['company_name'], $after->company_name);
        $this->assertSame($beforeCheckIn, $after->check_in_at->format('Y-m-d H:i:s'));
        $this->assertSame($before['check_out_at'], $after->check_out_at);
        $this->assertSame($before['checkout_source'], $after->checkout_source);
        $this->assertSame($before['checked_out_by'], $after->checked_out_by);
    }

    // 9. Backfill hanya mengisi nilai NULL.
    public function test_backfill_fills_only_null_checkout(): void
    {
        $sa = $this->makeSuperadmin();
        $uNull = Unit::create(['unit_name' => 'NullU '.Str::random(3), 'unit_level' => 'uid', 'is_active' => true, 'guest_book_checkout_qr_code' => null]);
        $uSet = $this->makeUnit('SetU');
        $uSet->guest_book_checkout_qr_code = (string) Str::uuid();
        $uSet->save();

        $this->actingAs($sa)->post('/admin/guest-book/qr/backfill')->assertRedirect();
        $uNull->refresh();
        $uSet->refresh();
        $this->assertNotNull($uNull->guest_book_checkout_qr_code);
    }

    // 10. Backfill tidak mengubah QR yang sudah terisi.
    public function test_backfill_does_not_change_existing_checkout(): void
    {
        $sa = $this->makeSuperadmin();
        $uSet = $this->makeUnit('Keep');
        $existing = (string) Str::uuid();
        $uSet->guest_book_checkout_qr_code = $existing;
        $uSet->save();
        $checkin = $uSet->fresh()->guest_book_qr_code;

        $this->actingAs($sa)->post('/admin/guest-book/qr/backfill')->assertRedirect();
        $uSet->refresh();
        $this->assertSame($existing, $uSet->guest_book_checkout_qr_code);
        $this->assertSame($checkin, $uSet->guest_book_qr_code);
    }

    // 11. Backfill aman dijalankan berulang.
    public function test_backfill_idempotent(): void
    {
        $sa = $this->makeSuperadmin();
        $uNull = Unit::create(['unit_name' => 'Idem '.Str::random(3), 'unit_level' => 'uid', 'is_active' => true, 'guest_book_checkout_qr_code' => null]);
        $this->actingAs($sa)->post('/admin/guest-book/qr/backfill')->assertRedirect();
        $first = $uNull->fresh()->guest_book_checkout_qr_code;
        $this->actingAs($sa)->post('/admin/guest-book/qr/backfill')->assertRedirect();
        $this->assertSame($first, $uNull->fresh()->guest_book_checkout_qr_code);
        $resp = $this->actingAs($sa)->get('/admin/guest-book/qr');
        $page = $resp->original->getData()['page'];
        $this->assertSame(0, $page['props']['missingCheckoutCount']);
    }

    // 12. UUID yang dihasilkan unik.
    public function test_generated_uuids_unique(): void
    {
        $sa = $this->makeSuperadmin();
        $units = collect();
        for ($i = 0; $i < 5; $i++) {
            $units->push(Unit::create(['unit_name' => 'U'.$i.' '.Str::random(3), 'unit_level' => 'uid', 'is_active' => true, 'guest_book_checkout_qr_code' => null]));
        }
        $this->actingAs($sa)->post('/admin/guest-book/qr/backfill')->assertRedirect();
        $all = Unit::whereIn('id', $units->pluck('id'))->get();
        $checkins = $all->pluck('guest_book_qr_code');
        $checkouts = $all->pluck('guest_book_checkout_qr_code');
        $combined = $checkins->merge($checkouts)->filter();
        $this->assertSame($combined->count(), $combined->unique()->count());

        // regenerate uniqueness
        $u = $units->first()->fresh();
        $oldIn = $u->guest_book_qr_code;
        $oldOut = $u->guest_book_checkout_qr_code;
        $this->actingAs($sa)->post("/admin/guest-book/qr/{$u->id}/regenerate-checkin");
        $this->actingAs($sa)->post("/admin/guest-book/qr/{$u->id}/regenerate-checkout");
        $u->refresh();
        $others = Unit::where('id', '!=', $u->id)->get();
        $allVals = $others->pluck('guest_book_qr_code')->merge($others->pluck('guest_book_checkout_qr_code'))->push($u->guest_book_qr_code)->push($u->guest_book_checkout_qr_code)->filter();
        $this->assertSame($allVals->count(), $allVals->unique()->count());
        $this->assertNotSame($oldIn, $u->guest_book_qr_code);
        $this->assertNotSame($oldOut, $u->guest_book_checkout_qr_code);
    }

    // 13. Pengguna non-Superadmin tidak dapat menjalankan backfill atau regenerasi.
    public function test_non_superadmin_cannot_backfill_or_regenerate(): void
    {
        $unit = $this->makeUnit('NS');
        $admin = $this->makeAdmin($unit);
        $oldIn = $unit->guest_book_qr_code;
        $this->actingAs($admin)->post('/admin/guest-book/qr/backfill')->assertStatus(403);
        $this->actingAs($admin)->post("/admin/guest-book/qr/{$unit->id}/regenerate-checkin")->assertStatus(403);
        $this->assertSame($oldIn, $unit->fresh()->guest_book_qr_code);
    }
}
