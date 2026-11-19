<?php

namespace Tests\Feature\Admin;

use App\Models\Unit;
use App\Services\GuestBookQrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UnitGuestBookQrModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_unit_gets_both_qr_codes(): void
    {
        $unit = Unit::create(['unit_name' => 'U1', 'unit_level' => 'uid', 'is_active' => true]);
        $this->assertNotEmpty($unit->guest_book_qr_code);
        $this->assertNotEmpty($unit->guest_book_checkout_qr_code);
        $this->assertTrue(Str::isUuid($unit->guest_book_qr_code));
        $this->assertTrue(Str::isUuid($unit->guest_book_checkout_qr_code));
        $this->assertNotSame($unit->guest_book_qr_code, $unit->guest_book_checkout_qr_code);
    }

    public function test_explicit_qr_not_overwritten_on_create(): void
    {
        $in = (string) Str::uuid();
        $out = (string) Str::uuid();
        $unit = Unit::create([
            'unit_name' => 'U2', 'unit_level' => 'uid', 'is_active' => true,
            'guest_book_qr_code' => $in,
            'guest_book_checkout_qr_code' => $out,
        ]);
        $this->assertSame($in, $unit->fresh()->guest_book_qr_code);
        $this->assertSame($out, $unit->fresh()->guest_book_checkout_qr_code);
    }

    public function test_update_does_not_change_qr_codes(): void
    {
        $unit = Unit::create(['unit_name' => 'U3', 'unit_level' => 'uid', 'is_active' => true]);
        $in = $unit->guest_book_qr_code;
        $out = $unit->guest_book_checkout_qr_code;
        $unit->update(['unit_name' => 'U3 changed', 'is_active' => false]);
        $unit->refresh();
        $this->assertSame($in, $unit->guest_book_qr_code);
        $this->assertSame($out, $unit->guest_book_checkout_qr_code);
    }

    public function test_backfill_only_null_checkout_and_idempotent(): void
    {
        $filled = Unit::create(['unit_name' => 'F', 'unit_level' => 'uid', 'is_active' => true]);
        $filledOut = $filled->guest_book_checkout_qr_code;
        $filledIn = $filled->guest_book_qr_code;
        $nullUnit = Unit::create(['unit_name' => 'N', 'unit_level' => 'uid', 'is_active' => true]);
        $nullUnit->updateQuietly(['guest_book_checkout_qr_code' => null]);
        $this->assertNull($nullUnit->fresh()->guest_book_checkout_qr_code);

        $service = app(GuestBookQrService::class);
        $count = $service->backfillMissingCheckoutQrCodes();
        $this->assertSame(1, $count);
        $this->assertNotNull($nullUnit->fresh()->guest_book_checkout_qr_code);
        $this->assertSame($filledOut, $filled->fresh()->guest_book_checkout_qr_code);
        $this->assertSame($filledIn, $filled->fresh()->guest_book_qr_code);

        $count2 = $service->backfillMissingCheckoutQrCodes();
        $this->assertSame(0, $count2);
        $this->assertSame($nullUnit->fresh()->guest_book_checkout_qr_code, $nullUnit->fresh()->guest_book_checkout_qr_code);
    }
}
