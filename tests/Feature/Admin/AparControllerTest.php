<?php

namespace Tests\Feature\Admin;

use App\Models\Apar;
use App\Models\AparInspection;
use App\Models\Security;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Scheduling\SchedulingTestCase;

class AparControllerTest extends SchedulingTestCase
{
    private function createApar(Unit $unit, array $attributes = []): Apar
    {
        return Apar::query()->create(array_merge([
            'unit_id' => $unit->id,
            'apar_code' => 'APR-'.strtoupper(Str::random(6)),
            'apar_type' => 'co2',
            'brand' => 'BrandX',
            'capacity' => '6 Kg',
            'placement' => 'Lobby',
            'qr_code' => (string) Str::uuid(),
            'is_active' => true,
        ], $attributes));
    }

    private function createInspection(Apar $apar, Security $security): AparInspection
    {
        return AparInspection::query()->create([
            'apar_id' => $apar->id,
            'unit_id' => $apar->unit_id,
            'security_id' => $security->id,
            'inspected_at' => now(),
            'result' => 'ok',
            'notes' => null,
            'unit_name_snapshot' => $apar->unit?->unit_name ?? 'Unit',
            'unit_code_snapshot' => $apar->unit?->unit_code,
            'apar_code_snapshot' => $apar->apar_code,
            'apar_type_snapshot' => $apar->apar_type,
            'brand_snapshot' => $apar->brand,
            'capacity_snapshot' => $apar->capacity,
            'serial_number_snapshot' => $apar->serial_number,
            'placement_snapshot' => $apar->placement,
            'apar_expiry_date_snapshot' => $apar->expiry_date,
        ]);
    }

    public function test_admin_can_view_apar_list_within_unit_scope(): void
    {
        $root = $this->createUnit('uid');
        $child = $this->createUnit('ulp', $root);
        $other = $this->createUnit('ulp');

        $admin = $this->createAdmin($root);
        $inScope = $this->createApar($child, ['apar_code' => 'APR-IN']);
        $outScope = $this->createApar($other, ['apar_code' => 'APR-OUT']);

        $response = $this->actingAs($admin)->get('/admin/apar?unit_id=');

        $response->assertOk();
        $response->assertSee('APR-IN');
        $response->assertDontSee('APR-OUT');
    }

    public function test_superadmin_can_view_apar_across_units(): void
    {
        $unitA = $this->createUnit('ulp');
        $unitB = $this->createUnit('ulp');
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $this->createApar($unitA, ['apar_code' => 'APR-A']);
        $this->createApar($unitB, ['apar_code' => 'APR-B']);

        $response = $this->actingAs($superadmin)->get('/admin/apar?unit_id=');

        $response->assertOk();
        $response->assertSee('APR-A');
        $response->assertSee('APR-B');
    }

    public function test_admin_cannot_access_apar_outside_scope(): void
    {
        $adminUnit = $this->createUnit('ulp');
        $otherUnit = $this->createUnit('ulp');
        $admin = $this->createAdmin($adminUnit);
        $apar = $this->createApar($otherUnit, ['apar_code' => 'APR-NO']);

        $this->actingAs($admin)->get('/admin/apar/'.$apar->id)->assertForbidden();
        $this->actingAs($admin)->get('/admin/apar/'.$apar->id.'/edit')->assertForbidden();
        $this->actingAs($admin)->put('/admin/apar/'.$apar->id, $this->validPayload($adminUnit))->assertForbidden();
        $this->actingAs($admin)->post('/admin/apar/'.$apar->id.'/toggle-active')->assertForbidden();
    }

    public function test_security_is_denied_from_apar_master_endpoints(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $apar = $this->createApar($unit);

        $this->actingAs($security->user)->get('/admin/apar')->assertForbidden();
        $this->actingAs($security->user)->get('/admin/apar/create')->assertForbidden();
        $this->actingAs($security->user)->get('/admin/apar/'.$apar->id)->assertForbidden();
    }

    public function test_admin_can_store_valid_apar(): void
    {
        Storage::fake('public');
        $unit = $this->createUnit('ulp');
        $admin = $this->createAdmin($unit);

        $payload = $this->validPayload($unit, [
            'apar_code' => 'APR-MANUAL',
            'qr_code' => '11111111-1111-1111-1111-111111111111',
            'photo' => UploadedFile::fake()->image('apar.jpg'),
        ]);

        $response = $this->actingAs($admin)->post('/admin/apar', $payload);

        $response->assertRedirect('/admin/apar');
        $this->assertDatabaseHas('apars', [
            'apar_code' => 'APAR-0001',
            'unit_id' => $unit->id,
            'apar_type' => 'co2',
            'brand' => 'BrandX',
            'placement' => 'Lobby',
        ]);
        $apar = Apar::query()->where('apar_code', 'APAR-0001')->firstOrFail();
        $this->assertSame('APAR-0001', $apar->apar_code);
        $this->assertDatabaseMissing('apars', ['apar_code' => 'APR-MANUAL']);
        $this->assertNotNull($apar->qr_code);
        $this->assertTrue(Str::isUuid($apar->qr_code));
        $this->assertNotSame('11111111-1111-1111-1111-111111111111', $apar->qr_code);
        $this->assertNotNull($apar->photo);
        $this->assertStringStartsWith('apar-photos/', $apar->photo);
        Storage::disk('public')->assertExists($apar->photo);
    }

    public function test_auto_generated_apar_codes_are_sequential_and_client_code_is_ignored(): void
    {
        $unit = $this->createUnit('ulp');
        $admin = $this->createAdmin($unit);

        $this->actingAs($admin)->post('/admin/apar', $this->validPayload($unit, [
            'apar_code' => 'APAR-9999',
        ]))->assertRedirect('/admin/apar');

        $this->actingAs($admin)->post('/admin/apar', $this->validPayload($unit, [
            'apar_code' => 'APAR-0001',
        ]))->assertRedirect('/admin/apar');

        $codes = Apar::query()->orderBy('id')->pluck('apar_code')->all();
        $this->assertSame(['APAR-0001', 'APAR-0002'], $codes);
        $this->assertSame(count($codes), count(array_unique($codes)));
    }

    public function test_admin_cannot_create_apar_for_unit_outside_scope(): void
    {
        $adminUnit = $this->createUnit('ulp');
        $otherUnit = $this->createUnit('ulp');
        $admin = $this->createAdmin($adminUnit);

        $response = $this->actingAs($admin)->from('/admin/apar/create')->post('/admin/apar', $this->validPayload($otherUnit, [
            'apar_code' => 'APR-OUT',
        ]));

        $response->assertSessionHasErrors('unit_id');
        $this->assertDatabaseMissing('apars', ['apar_code' => 'APR-OUT']);
    }

    public function test_edit_apar_does_not_change_qr_uuid_and_replaces_photo_safely(): void
    {
        Storage::fake('public');
        $unit = $this->createUnit('ulp');
        $admin = $this->createAdmin($unit);

        $oldPath = UploadedFile::fake()->image('old.jpg')->storeAs('apar-photos', 'old.jpg', 'public');
        $apar = $this->createApar($unit, [
            'apar_code' => 'APR-EDIT',
            'photo' => $oldPath,
        ]);
        $originalQr = $apar->qr_code;

        $response = $this->actingAs($admin)->put('/admin/apar/'.$apar->id, $this->validPayload($unit, [
            'apar_code' => 'APR-EDIT-2',
            'brand' => 'BrandY',
            'photo' => UploadedFile::fake()->image('new.jpg'),
        ]));

        $response->assertRedirect('/admin/apar/'.$apar->id);
        $apar->refresh();
        $this->assertSame($originalQr, $apar->qr_code);
        $this->assertSame('APR-EDIT', $apar->apar_code);
        $this->assertSame('BrandY', $apar->brand);
        $this->assertNotSame($oldPath, $apar->photo);
        $this->assertStringStartsWith('apar-photos/', $apar->photo);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($apar->photo);
    }

    public function test_toggle_active_and_deactivate_does_not_delete_apar(): void
    {
        $unit = $this->createUnit('ulp');
        $admin = $this->createAdmin($unit);
        $apar = $this->createApar($unit, ['apar_code' => 'APR-TOG', 'is_active' => true]);

        $this->actingAs($admin)->post('/admin/apar/'.$apar->id.'/toggle-active')->assertRedirect();
        $this->assertFalse((bool) $apar->fresh()->is_active);

        $this->actingAs($admin)->post('/admin/apar/'.$apar->id.'/toggle-active')->assertRedirect();
        $this->assertTrue((bool) $apar->fresh()->is_active);
        $this->assertDatabaseHas('apars', ['id' => $apar->id, 'apar_code' => 'APR-TOG']);
    }

    public function test_inspection_history_unchanged_when_apar_edited_or_deactivated(): void
    {
        $unit = $this->createUnit('ulp');
        $admin = $this->createAdmin($unit);
        $security = $this->createSecurity($unit);
        $apar = $this->createApar($unit, ['apar_code' => 'APR-HIST']);
        $inspection = $this->createInspection($apar, $security);
        $snapshot = $inspection->only([
            'id', 'apar_id', 'unit_id', 'security_id', 'inspected_at', 'result', 'notes',
            'unit_name_snapshot', 'unit_code_snapshot', 'apar_code_snapshot', 'apar_type_snapshot',
            'brand_snapshot', 'capacity_snapshot', 'serial_number_snapshot', 'placement_snapshot',
            'apar_expiry_date_snapshot',
        ]);

        $this->actingAs($admin)->put('/admin/apar/'.$apar->id, $this->validPayload($unit, [
            'apar_code' => 'APR-HIST-2',
            'brand' => 'Changed',
            'is_active' => true,
        ]))->assertRedirect();

        $this->actingAs($admin)->post('/admin/apar/'.$apar->id.'/toggle-active')->assertRedirect();

        $inspection->refresh();
        foreach ($snapshot as $key => $value) {
            if ($key === 'inspected_at') {
                $this->assertEquals($value, $inspection->inspected_at);
                continue;
            }
            $this->assertSame($value, $inspection->getAttribute($key), "Field {$key} changed");
        }
        $this->assertSame(1, AparInspection::query()->where('apar_id', $apar->id)->count());
    }

    private function validPayload(Unit $unit, array $overrides = []): array
    {
        return array_merge([
            'unit_id' => $unit->id,
            'apar_type' => 'co2',
            'brand' => 'BrandX',
            'capacity' => '6 Kg',
            'placement' => 'Lobby',
            'serial_number' => null,
            'procurement_date' => null,
            'installation_date' => null,
            'recharge_date' => null,
            'expiry_date' => null,
            'is_active' => true,
        ], $overrides);
    }
}
