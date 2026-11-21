<?php

namespace Tests\Feature\GuestBook;

use App\Models\GuestVisit;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuestCheckInTest extends TestCase
{
    use RefreshDatabase;

    private function makeUnit(array $overrides = []): Unit
    {
        return Unit::create(array_merge([
            'unit_name' => 'UNIT TEST '.Str::upper(Str::random(4)),
            'unit_level' => 'uid',
            'is_active' => true,
        ], $overrides));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Budi Tamu',
            'phone_number' => '08123456789',
            'company_name' => 'PT Contoh',
            'position' => 'Staff',
            'visit_purpose' => 'Rapat koordinasi',
            'person_to_meet' => 'Andi',
            'vehicle_plate_number' => 'BL 1234 AB',
            'consent_given' => '1',
        ], $overrides);
    }

    public function test_valid_qr_active_unit_shows_form(): void
    {
        $unit = $this->makeUnit();
        $uuid = $unit->fresh()->guest_book_qr_code;

        $response = $this->get("/guest-book/{$uuid}");
        $response->assertOk();
        $response->assertSee($unit->unit_name, false);
        $response->assertDontSee('"unit_id"', false);
    }

    public function test_invalid_qr_returns_404(): void
    {
        $this->get('/guest-book/not-a-uuid')->assertStatus(404);
        $this->get('/guest-book/'.Str::uuid())->assertStatus(404);
    }

    public function test_inactive_unit_rejected(): void
    {
        $unit = $this->makeUnit(['is_active' => false]);
        $uuid = $unit->fresh()->guest_book_qr_code;

        $this->get("/guest-book/{$uuid}")->assertStatus(404);
        $this->post("/guest-book/{$uuid}", $this->validPayload())->assertStatus(422);
        $this->assertDatabaseCount('guest_visits', 0);
    }

    public function test_unit_determined_from_qr_not_browser(): void
    {
        $unitA = $this->makeUnit(['unit_name' => 'UNIT A']);
        $unitB = $this->makeUnit(['unit_name' => 'UNIT B']);
        $uuidA = $unitA->fresh()->guest_book_qr_code;

        $this->post("/guest-book/{$uuidA}", $this->validPayload([
            'unit_id' => $unitB->id,
        ]))->assertSessionHasErrors('unit_id');

        $this->assertDatabaseCount('guest_visits', 0);
    }

    public function test_required_fields_and_consent_validation(): void
    {
        $unit = $this->makeUnit();
        $uuid = $unit->fresh()->guest_book_qr_code;

        $this->post("/guest-book/{$uuid}", [])->assertSessionHasErrors([
            'full_name', 'phone_number', 'company_name', 'position', 'visit_purpose', 'person_to_meet', 'consent_given',
        ]);
        $this->assertDatabaseCount('guest_visits', 0);
    }

    public function test_successful_checkin_stores_server_time_and_snapshot(): void
    {
        $this->travelTo('2026-11-17 10:00:00');
        $unit = $this->makeUnit(['unit_name' => 'UNIT SNAP']);
        $unit = $unit->fresh();
        $uuid = $unit->guest_book_qr_code;

        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->post("/guest-book/{$uuid}", $this->validPayload(['check_in_at' => '2000-01-01 00:00:00']));

        $response->assertSessionHasErrors('check_in_at');
        $this->assertDatabaseCount('guest_visits', 0);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->post("/guest-book/{$uuid}", $this->validPayload());

        $response->assertRedirect(route('guest-book.success'));
        $visit = GuestVisit::first();
        $this->assertNotNull($visit);
        $this->assertSame($unit->id, $visit->unit_id);
        $this->assertSame($uuid, $visit->guest_book_qr_code);
        $this->assertSame('UNIT SNAP', $visit->unit_name_snapshot);
        $this->assertSame($unit->unit_code, $visit->unit_code_snapshot);
        $this->assertTrue((bool) $visit->consent_given);
        $this->assertNotNull($visit->consent_at);
        $this->assertEquals('2026-11-17 10:00:00', $visit->check_in_at->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-11-17 10:00:00', $visit->consent_at->format('Y-m-d H:i:s'));
        $this->assertSame('10.0.0.5', $visit->ip_address);
        $this->assertNull($visit->selfie_path);
        $this->assertNull($visit->signature_path);
    }

    public function test_private_file_storage_for_selfie_and_signature(): void
    {
        Storage::fake('public');
        $unit = $this->makeUnit();
        $uuid = $unit->fresh()->guest_book_qr_code;
        $selfie = UploadedFile::fake()->image('selfie.jpg', 200, 200)->size(500);
        $signature = 'data:image/png;base64,'.base64_encode(str_repeat('A', 100));

        $this->post("/guest-book/{$uuid}", $this->validPayload([
            'selfie' => $selfie,
            'signature' => $signature,
        ]))->assertRedirect(route('guest-book.success'));

        $visit = GuestVisit::first();
        $this->assertNotNull($visit->selfie_path);
        $this->assertNotNull($visit->signature_path);
        $this->assertStringStartsWith('guest-book/selfie/', $visit->selfie_path);
        $this->assertStringStartsWith('guest-book/signature/', $visit->signature_path);
        Storage::disk('public')->assertExists($visit->selfie_path);
        Storage::disk('public')->assertExists($visit->signature_path);
    }

    public function test_rate_limiting_submit(): void
    {
        $unit = $this->makeUnit();
        $uuid = $unit->fresh()->guest_book_qr_code;
        RateLimiter::clear('guest-checkin-submit|127.0.0.1');

        for ($i = 0; $i < 10; $i++) {
            $this->post("/guest-book/{$uuid}", $this->validPayload(['full_name' => 'Tamu '.$i]));
        }
        $this->post("/guest-book/{$uuid}", $this->validPayload(['full_name' => 'Tamu 11']))->assertStatus(429);
    }

    public function test_success_page_does_not_leak_other_visits(): void
    {
        $unit = $this->makeUnit();
        $uuid = $unit->fresh()->guest_book_qr_code;
        $this->post("/guest-book/{$uuid}", $this->validPayload(['full_name' => 'Rahasia Satu']))->assertRedirect(route('guest-book.success'));

        $response = $this->get(route('guest-book.success'));
        $response->assertOk();
        $response->assertDontSee('Rahasia Satu', false);
        $response->assertDontSee($unit->unit_name, false);
        $response->assertDontSee('08123456789', false);
    }
}
