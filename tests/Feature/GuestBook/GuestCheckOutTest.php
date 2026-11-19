<?php

namespace Tests\Feature\GuestBook;

use App\Models\GuestVisit;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuestCheckOutTest extends TestCase
{
    use RefreshDatabase;

    private function makeUnit(array $overrides = []): Unit
    {
        return Unit::create(array_merge([
            'unit_name' => 'UNIT '.Str::upper(Str::random(4)),
            'unit_level' => 'uid',
            'is_active' => true,
        ], $overrides));
    }

    private function makeVisit(Unit $unit, array $overrides = []): GuestVisit
    {
        $unit = $unit->fresh();
        return GuestVisit::create(array_merge([
            'unit_id' => $unit->id,
            'guest_book_qr_code' => $unit->guest_book_qr_code,
            'full_name' => 'Tamu A',
            'phone_number' => '08123456789',
            'company_name' => 'PT A',
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

    public function test_valid_checkout_qr_shows_form(): void
    {
        $unit = $this->makeUnit();
        $uuid = $unit->fresh()->guest_book_checkout_qr_code;
        $this->get("/guest-book/checkout/{$uuid}")->assertOk()->assertSee($unit->unit_name, false);
    }

    public function test_invalid_checkout_qr_rejected(): void
    {
        $this->get('/guest-book/checkout/not-a-uuid')->assertStatus(404);
        $this->get('/guest-book/checkout/'.Str::uuid())->assertStatus(404);
    }

    public function test_old_regenerated_qr_rejected_even_if_visit_active(): void
    {
        $unit = $this->makeUnit();
        $old = $unit->fresh()->guest_book_checkout_qr_code;
        $visit = $this->makeVisit($unit); // active
        $unit->update(['guest_book_checkout_qr_code' => Str::uuid()->toString()]);

        $this->get("/guest-book/checkout/{$old}")->assertStatus(404);
        $this->post("/guest-book/checkout/{$old}/lookup", ['phone_number' => '08123456789'])->assertStatus(422);
        $this->assertNull($visit->fresh()->check_out_at);
    }

    public function test_inactive_unit_checkout_allowed_for_active_visit(): void
    {
        $unit = $this->makeUnit(['is_active' => false]);
        $uuid = $unit->fresh()->guest_book_checkout_qr_code;
        $visit = $this->makeVisit($unit);

        $this->post("/guest-book/checkout/{$uuid}/lookup", ['phone_number' => '08123456789'])
            ->assertRedirect()
            ->assertSessionHas('candidates');

        $this->post("/guest-book/checkout/{$uuid}/confirm", ['phone_number' => '08123456789', 'guest_visit_id' => $visit->id])
            ->assertRedirect(route('guest-book.checkout.success'));

        $this->assertNotNull($visit->fresh()->check_out_at);
    }

    public function test_unit_determined_from_qr_not_browser(): void
    {
        $unitA = $this->makeUnit(['unit_name' => 'UNIT A']);
        $unitB = $this->makeUnit(['unit_name' => 'UNIT B']);
        $visitB = $this->makeVisit($unitB);

        $this->post("/guest-book/checkout/{$unitA->fresh()->guest_book_checkout_qr_code}/lookup", [
            'phone_number' => '08123456789',
            'unit_id' => $unitB->id,
        ])->assertSessionHasErrors('unit_id');

        $this->post("/guest-book/checkout/{$unitA->fresh()->guest_book_checkout_qr_code}/confirm", [
            'phone_number' => '08123456789',
            'guest_visit_id' => $visitB->id,
        ])->assertSessionHasErrors('phone_number');
        $this->assertNull($visitB->fresh()->check_out_at);
    }

    public function test_phone_not_found_returns_generic_message(): void
    {
        $unit = $this->makeUnit();
        $uuid = $unit->fresh()->guest_book_checkout_qr_code;
        $this->makeVisit($unit);

        $this->post("/guest-book/checkout/{$uuid}/lookup", ['phone_number' => '000'])
            ->assertSessionHasErrors('phone_number');
        $this->assertSame('Data kunjungan tidak ditemukan atau sudah selesai.', session('errors')->get('phone_number')[0] ?? null);
    }

    public function test_single_active_visit_and_checkout_success_with_server_time(): void
    {
        $this->travelTo('2026-11-17 12:00:00');
        $unit = $this->makeUnit();
        $uuid = $unit->fresh()->guest_book_checkout_qr_code;
        $visit = $this->makeVisit($unit);

        $this->post("/guest-book/checkout/{$uuid}/lookup", ['phone_number' => '0812-3456-789'])
            ->assertSessionHas('single_candidate')
            ->assertSessionHas('candidates');

        $this->post("/guest-book/checkout/{$uuid}/confirm", [
            'phone_number' => '0812-3456-789',
            'guest_visit_id' => $visit->id,
            'check_out_at' => '2000-01-01 00:00:00',
        ])->assertSessionHasErrors('check_out_at');

        $this->post("/guest-book/checkout/{$uuid}/confirm", [
            'phone_number' => '0812-3456-789',
            'guest_visit_id' => $visit->id,
        ])->assertRedirect(route('guest-book.checkout.success'));

        $visit->refresh();
        $this->assertEquals('2026-11-17 12:00:00', $visit->check_out_at->format('Y-m-d H:i:s'));
        $this->assertSame('guest', $visit->checkout_source);
        $this->assertNull($visit->checked_out_by);
    }

    public function test_multiple_active_visits_same_phone_show_choices(): void
    {
        $unit = $this->makeUnit();
        $uuid = $unit->fresh()->guest_book_checkout_qr_code;
        $v1 = $this->makeVisit($unit, ['full_name' => 'A', 'company_name' => 'X', 'check_in_at' => now()->subHours(2)]);
        $v2 = $this->makeVisit($unit, ['full_name' => 'B', 'company_name' => 'Y', 'check_in_at' => now()->subHour()]);

        $resp = $this->post("/guest-book/checkout/{$uuid}/lookup", ['phone_number' => '08123456789']);
        $resp->assertSessionHas('candidates');
        $c = session('candidates');
        $this->assertCount(2, $c);
        $ids = array_column($c, 'id');
        $this->assertContains($v1->id, $ids);
        $this->assertContains($v2->id, $ids);
    }

    public function test_checkout_idempotent_does_not_overwrite_first_time(): void
    {
        $this->travelTo('2026-11-17 12:00:00');
        $unit = $this->makeUnit();
        $uuid = $unit->fresh()->guest_book_checkout_qr_code;
        $visit = $this->makeVisit($unit);

        $this->post("/guest-book/checkout/{$uuid}/confirm", ['phone_number' => '08123456789', 'guest_visit_id' => $visit->id])
            ->assertRedirect(route('guest-book.checkout.success'));
        $first = $visit->fresh()->check_out_at->format('Y-m-d H:i:s');

        $this->travelTo('2026-11-17 13:00:00');
        $this->post("/guest-book/checkout/{$uuid}/confirm", ['phone_number' => '08123456789', 'guest_visit_id' => $visit->id])
            ->assertRedirect(route('guest-book.checkout.success'));

        $this->assertEquals($first, $visit->fresh()->check_out_at->format('Y-m-d H:i:s'));
    }

    public function test_rate_limiting_checkout_submit(): void
    {
        $unit = $this->makeUnit();
        $uuid = $unit->fresh()->guest_book_checkout_qr_code;
        $this->makeVisit($unit);
        RateLimiter::clear('guest-checkout-submit|127.0.0.1');
        RateLimiter::clear('guest-checkout-failures:127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->post("/guest-book/checkout/{$uuid}/lookup", ['phone_number' => '08123456789']);
        }
        $this->post("/guest-book/checkout/{$uuid}/lookup", ['phone_number' => '08123456789'])->assertStatus(429);
    }

    public function test_failure_cooldown_after_repeated_not_found(): void
    {
        $unit = $this->makeUnit();
        $uuid = $unit->fresh()->guest_book_checkout_qr_code;
        $this->makeVisit($unit);
        RateLimiter::clear('guest-checkout-failures:127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->post("/guest-book/checkout/{$uuid}/lookup", ['phone_number' => '000']);
        }
        $this->post("/guest-book/checkout/{$uuid}/lookup", ['phone_number' => '000'])->assertStatus(429);
    }

    public function test_error_message_does_not_leak_other_visitors(): void
    {
        $unit = $this->makeUnit();
        $uuid = $unit->fresh()->guest_book_checkout_qr_code;
        $this->makeVisit($unit, ['full_name' => 'Rahasia', 'company_name' => 'PT Rahasia']);

        $resp = $this->post("/guest-book/checkout/{$uuid}/lookup", ['phone_number' => '000']);
        $resp->assertSessionHasErrors('phone_number');
        $resp->assertDontSee('Rahasia', false);
        $resp->assertDontSee('PT Rahasia', false);
    }

    public function test_checkout_qr_null_rejected_safely(): void
    {
        $unit = Unit::create([
            'unit_name' => 'UNIT NULL QR',
            'unit_level' => 'uid',
            'is_active' => true,
            'guest_book_checkout_qr_code' => null,
        ]);
        // Unit boot sets checkout qr; force null to simulate existing unit without backfill
        Unit::whereKey($unit->id)->update(['guest_book_checkout_qr_code' => null]);
        $uuid = Str::uuid()->toString();
        $this->get("/guest-book/checkout/{$uuid}")->assertStatus(404);
    }
}
