<?php

namespace Tests\Feature\Admin;

use App\Models\Hydrant;
use App\Models\HydrantInspection;
use App\Models\Security;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Scheduling\SchedulingTestCase;

class HydrantControllerTest extends SchedulingTestCase
{
    private function createHydrant(Unit $unit, array $attributes = []): Hydrant
    {
        return Hydrant::query()->create(array_merge([
            'unit_id' => $unit->id,
            'hydrant_code' => 'HYD-'.strtoupper(Str::random(6)),
            'name' => 'Hydrant Utama',
            'hydrant_type' => 'fire_pump_diesel',
            'brand' => 'BrandX',
            'model' => 'ModelX',
            'placement' => 'Area Parkir',
            'qr_code' => (string) Str::uuid(),
            'is_active' => true,
        ], $attributes));
    }

    private function createInspection(Hydrant $hydrant, Security $security): HydrantInspection
    {
        return HydrantInspection::query()->create([
            'hydrant_id' => $hydrant->id,
            'unit_id' => $hydrant->unit_id,
            'security_id' => $security->id,
            'inspected_at' => now(),
            'result' => 'ok',
            'notes' => null,
            'unit_name_snapshot' => $hydrant->unit?->unit_name ?? 'Unit',
            'unit_code_snapshot' => $hydrant->unit?->unit_code,
            'hydrant_code_snapshot' => $hydrant->hydrant_code,
            'name_snapshot' => $hydrant->name,
            'hydrant_type_snapshot' => $hydrant->hydrant_type,
            'brand_snapshot' => $hydrant->brand,
            'model_snapshot' => $hydrant->model,
            'serial_number_snapshot' => $hydrant->serial_number,
            'placement_snapshot' => $hydrant->placement,
            'last_maintenance_date_snapshot' => $hydrant->last_maintenance_date,
        ]);
    }

    public function test_admin_can_view_hydrant_list_within_unit_scope(): void
    {
        $root = $this->createUnit('uid');
        $child = $this->createUnit('ulp', $root);
        $other = $this->createUnit('ulp');

        $admin = $this->createAdmin($root);
        $this->createHydrant($child, ['hydrant_code' => 'HYD-IN', 'name' => 'Hydrant Dalam']);
        $this->createHydrant($other, ['hydrant_code' => 'HYD-OUT', 'name' => 'Hydrant Luar']);

        $response = $this->actingAs($admin)->get('/admin/hydrant?unit_id=');

        $response->assertOk();
        $response->assertSee('HYD-IN');
        $response->assertSee('Hydrant Dalam');
        $response->assertDontSee('HYD-OUT');
    }

    public function test_superadmin_can_view_hydrant_across_units(): void
    {
        $unitA = $this->createUnit('ulp');
        $unitB = $this->createUnit('ulp');
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $this->createHydrant($unitA, ['hydrant_code' => 'HYD-A']);
        $this->createHydrant($unitB, ['hydrant_code' => 'HYD-B']);

        $response = $this->actingAs($superadmin)->get('/admin/hydrant?unit_id=');

        $response->assertOk();
        $response->assertSee('HYD-A');
        $response->assertSee('HYD-B');
    }

    public function test_admin_cannot_access_hydrant_outside_scope(): void
    {
        $adminUnit = $this->createUnit('ulp');
        $otherUnit = $this->createUnit('ulp');
        $admin = $this->createAdmin($adminUnit);
        $hydrant = $this->createHydrant($otherUnit, ['hydrant_code' => 'HYD-NO']);

        $this->actingAs($admin)->get('/admin/hydrant/'.$hydrant->id)->assertForbidden();
        $this->actingAs($admin)->get('/admin/hydrant/'.$hydrant->id.'/edit')->assertForbidden();
        $this->actingAs($admin)->put('/admin/hydrant/'.$hydrant->id, $this->validPayload($otherUnit))->assertForbidden();
        $this->actingAs($admin)->post('/admin/hydrant/'.$hydrant->id.'/toggle-active')->assertForbidden();
    }

    public function test_security_is_denied_from_hydrant_master_endpoints(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $hydrant = $this->createHydrant($unit);

        $this->actingAs($security->user)->get('/admin/hydrant')->assertForbidden();
        $this->actingAs($security->user)->get('/admin/hydrant/create')->assertForbidden();
        $this->actingAs($security->user)->get('/admin/hydrant/'.$hydrant->id)->assertForbidden();
    }

    public function test_admin_can_store_valid_hydrant_with_uuid_and_photo(): void
    {
        Storage::fake('public');
        $unit = $this->createUnit('ulp');
        $admin = $this->createAdmin($unit);

        $payload = $this->validPayload($unit, [
            'hydrant_code' => 'HYD-MANUAL',
            'qr_code' => '11111111-1111-1111-1111-111111111111',
            'name' => 'Hydrant Baru',
            'hydrant_type' => 'fire_pump_diesel',
            'brand' => 'BrandY',
            'model' => 'ModelY',
            'serial_number' => 'SN-HYD-1',
            'procurement_date' => '2020-01-02',
            'installation_date' => '2020-02-03',
            'last_maintenance_date' => '2025-03-04',
            'placement' => 'Gedung A',
            'photo' => UploadedFile::fake()->image('hydrant.jpg'),
        ]);

        $response = $this->actingAs($admin)->post('/admin/hydrant', $payload);

        $response->assertRedirect('/admin/hydrant');
        $this->assertDatabaseHas('hydrants', [
            'hydrant_code' => 'HYD-0001',
            'unit_id' => $unit->id,
            'name' => 'Hydrant Baru',
            'hydrant_type' => 'fire_pump_diesel',
            'brand' => 'BrandY',
            'model' => 'ModelY',
            'placement' => 'Gedung A',
        ]);

        $hydrant = Hydrant::query()->where('hydrant_code', 'HYD-0001')->firstOrFail();
        $this->assertSame('HYD-0001', $hydrant->hydrant_code);
        $this->assertDatabaseMissing('hydrants', ['hydrant_code' => 'HYD-MANUAL']);
        $this->assertNotNull($hydrant->qr_code);
        $this->assertTrue(Str::isUuid($hydrant->qr_code));
        $this->assertNotSame('11111111-1111-1111-1111-111111111111', $hydrant->qr_code);
        $this->assertSame('2020-01-02', $hydrant->procurement_date?->format('Y-m-d'));
        $this->assertSame('2020-02-03', $hydrant->installation_date?->format('Y-m-d'));
        $this->assertSame('2025-03-04', $hydrant->last_maintenance_date?->format('Y-m-d'));
        $this->assertNotNull($hydrant->photo);
        $this->assertStringStartsWith('hydrant-photos/', $hydrant->photo);
        Storage::disk('public')->assertExists($hydrant->photo);
    }

    public function test_auto_generated_hydrant_codes_are_sequential_and_client_code_is_ignored(): void
    {
        $unit = $this->createUnit('ulp');
        $admin = $this->createAdmin($unit);

        $this->actingAs($admin)->post('/admin/hydrant', $this->validPayload($unit, [
            'hydrant_code' => 'HYD-9999',
        ]))->assertRedirect('/admin/hydrant');

        $this->actingAs($admin)->post('/admin/hydrant', $this->validPayload($unit, [
            'hydrant_code' => 'HYD-0001',
        ]))->assertRedirect('/admin/hydrant');

        $codes = Hydrant::query()->orderBy('id')->pluck('hydrant_code')->all();
        $this->assertSame(['HYD-0001', 'HYD-0002'], $codes);
        $this->assertSame(count($codes), count(array_unique($codes)));
    }

    public function test_admin_cannot_create_hydrant_for_unit_outside_scope(): void
    {
        $adminUnit = $this->createUnit('ulp');
        $otherUnit = $this->createUnit('ulp');
        $admin = $this->createAdmin($adminUnit);

        $response = $this->actingAs($admin)->from('/admin/hydrant/create')->post('/admin/hydrant', $this->validPayload($otherUnit));

        $response->assertSessionHasErrors('unit_id');
        $this->assertDatabaseCount('hydrants', 0);
    }

    public function test_only_fire_pump_diesel_can_be_created_in_mvp(): void
    {
        $unit = $this->createUnit('ulp');
        $admin = $this->createAdmin($unit);

        foreach (['fire_pump_electric', 'jockey_pump', 'hydrant_box', 'hydrant_pillar', 'siamese_connection', 'unknown_type'] as $type) {
            $this->actingAs($admin)->from('/admin/hydrant/create')->post('/admin/hydrant', $this->validPayload($unit, [
                'hydrant_type' => $type,
            ]))->assertRedirect('/admin/hydrant/create')->assertSessionHasErrors('hydrant_type');
        }

        $this->assertDatabaseCount('hydrants', 0);

        $this->actingAs($admin)->post('/admin/hydrant', $this->validPayload($unit, [
            'hydrant_type' => 'fire_pump_diesel',
        ]))->assertRedirect('/admin/hydrant');

        $this->assertDatabaseHas('hydrants', ['hydrant_type' => 'fire_pump_diesel']);
    }

    public function test_edit_hydrant_does_not_change_qr_uuid_and_replaces_photo_safely(): void
    {
        Storage::fake('public');
        $unit = $this->createUnit('ulp');
        $admin = $this->createAdmin($unit);

        $oldPath = UploadedFile::fake()->image('old.jpg')->storeAs('hydrant-photos', 'old.jpg', 'public');
        $hydrant = $this->createHydrant($unit, [
            'hydrant_code' => 'HYD-EDIT',
            'photo' => $oldPath,
        ]);
        $originalQr = $hydrant->qr_code;

        $response = $this->actingAs($admin)->put('/admin/hydrant/'.$hydrant->id, $this->validPayload($unit, [
            'hydrant_code' => 'HYD-EDIT-2',
            'brand' => 'BrandZ',
            'hydrant_type' => 'jockey_pump',
            'photo' => UploadedFile::fake()->image('new.jpg'),
        ]));

        $response->assertRedirect('/admin/hydrant/'.$hydrant->id);
        $hydrant->refresh();
        $this->assertSame($originalQr, $hydrant->qr_code);
        $this->assertSame('HYD-EDIT', $hydrant->hydrant_code);
        $this->assertSame('BrandZ', $hydrant->brand);
        $this->assertSame('fire_pump_diesel', $hydrant->hydrant_type);
        $this->assertNotSame($oldPath, $hydrant->photo);
        $this->assertStringStartsWith('hydrant-photos/', $hydrant->photo);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($hydrant->photo);
    }

    public function test_edit_preserves_legacy_hydrant_type_outside_mvp(): void
    {
        $unit = $this->createUnit('ulp');
        $admin = $this->createAdmin($unit);
        $hydrant = $this->createHydrant($unit, [
            'hydrant_code' => 'HYD-LEGACY',
            'hydrant_type' => 'hydrant_pillar',
        ]);

        $edit = $this->actingAs($admin)->get('/admin/hydrant/'.$hydrant->id.'/edit');
        $edit->assertOk()->assertInertia(fn ($page) => $page
            ->component('Admin/Hydrant/Edit')
            ->where('hydrant.hydrant_type', 'hydrant_pillar')
            ->where('hydrant.hydrant_code', 'HYD-LEGACY')
        );

        $this->actingAs($admin)->put('/admin/hydrant/'.$hydrant->id, $this->validPayload($unit, [
            'brand' => 'LegacyUpdated',
            'hydrant_type' => 'fire_pump_diesel',
        ]))->assertRedirect('/admin/hydrant/'.$hydrant->id);

        $hydrant->refresh();
        $this->assertSame('hydrant_pillar', $hydrant->hydrant_type);
        $this->assertSame('LegacyUpdated', $hydrant->brand);
        $this->assertSame('HYD-LEGACY', $hydrant->hydrant_code);
    }

    public function test_toggle_active_and_deactivate_does_not_delete_hydrant(): void
    {
        $unit = $this->createUnit('ulp');
        $admin = $this->createAdmin($unit);
        $hydrant = $this->createHydrant($unit, ['hydrant_code' => 'HYD-TOG', 'is_active' => true]);

        $this->actingAs($admin)->post('/admin/hydrant/'.$hydrant->id.'/toggle-active')->assertRedirect();
        $this->assertFalse((bool) $hydrant->fresh()->is_active);

        $this->actingAs($admin)->post('/admin/hydrant/'.$hydrant->id.'/toggle-active')->assertRedirect();
        $this->assertTrue((bool) $hydrant->fresh()->is_active);
        $this->assertDatabaseHas('hydrants', ['id' => $hydrant->id, 'hydrant_code' => 'HYD-TOG']);
    }

    public function test_inspection_history_unchanged_when_hydrant_edited_or_deactivated(): void
    {
        $unit = $this->createUnit('ulp');
        $admin = $this->createAdmin($unit);
        $security = $this->createSecurity($unit);
        $hydrant = $this->createHydrant($unit, ['hydrant_code' => 'HYD-HIST']);
        $inspection = $this->createInspection($hydrant, $security);

        $snapshot = $inspection->only([
            'id', 'hydrant_id', 'unit_id', 'security_id', 'inspected_at', 'result', 'notes',
            'unit_name_snapshot', 'unit_code_snapshot', 'hydrant_code_snapshot', 'name_snapshot',
            'hydrant_type_snapshot', 'brand_snapshot', 'model_snapshot', 'serial_number_snapshot',
            'placement_snapshot', 'last_maintenance_date_snapshot',
        ]);

        $this->actingAs($admin)->put('/admin/hydrant/'.$hydrant->id, $this->validPayload($unit, [
            'hydrant_code' => 'HYD-HIST-2',
            'brand' => 'Changed',
            'is_active' => true,
        ]))->assertRedirect();

        $this->actingAs($admin)->post('/admin/hydrant/'.$hydrant->id.'/toggle-active')->assertRedirect();

        $inspection->refresh();
        foreach ($snapshot as $key => $value) {
            if ($key === 'inspected_at') {
                $this->assertEquals($value, $inspection->inspected_at);
                continue;
            }
            $this->assertSame($value, $inspection->getAttribute($key), "Field {$key} changed");
        }
        $this->assertSame(1, HydrantInspection::query()->where('hydrant_id', $hydrant->id)->count());
    }

    public function test_update_removes_only_managed_hydrant_photo_paths(): void
    {
        Storage::fake('public');
        $unit = $this->createUnit('ulp');
        $admin = $this->createAdmin($unit);

        Storage::disk('public')->put('hydrant-photos/managed.jpg', 'x');
        Storage::disk('public')->put('unmanaged/keep.jpg', 'x');

        $managed = $this->createHydrant($unit, ['hydrant_code' => 'HYD-MGD', 'photo' => 'hydrant-photos/managed.jpg']);
        $unmanaged = $this->createHydrant($unit, ['hydrant_code' => 'HYD-UNM', 'photo' => 'unmanaged/keep.jpg']);

        $this->actingAs($admin)->put('/admin/hydrant/'.$managed->id, $this->validPayload($unit, [
            'remove_photo' => true,
        ]))->assertRedirect();

        $this->actingAs($admin)->put('/admin/hydrant/'.$unmanaged->id, $this->validPayload($unit, [
            'remove_photo' => true,
        ]))->assertRedirect();

        $this->assertNull($managed->fresh()->photo);
        $this->assertNull($unmanaged->fresh()->photo);
        Storage::disk('public')->assertMissing('hydrant-photos/managed.jpg');
        Storage::disk('public')->assertExists('unmanaged/keep.jpg');
    }

    private function validPayload(Unit $unit, array $overrides = []): array
    {
        return array_merge([
            'unit_id' => $unit->id,
            'name' => 'Hydrant Valid',
            'hydrant_type' => 'fire_pump_diesel',
            'brand' => 'BrandX',
            'model' => 'ModelX',
            'placement' => 'Area Parkir',
            'serial_number' => null,
            'procurement_date' => null,
            'installation_date' => null,
            'last_maintenance_date' => null,
            'is_active' => true,
        ], $overrides);
    }
}
