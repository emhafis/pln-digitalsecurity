<?php

namespace Tests\Feature\Asset;

use App\Models\Hydrant;
use App\Models\HydrantInspection;
use App\Models\HydrantInspectionItem;
use App\Models\HydrantInspectionPhoto;
use App\Models\Security;
use App\Models\Unit;
use App\Models\User;
use App\Support\AssetInspectionPhotoStorage;
use App\Support\AssetInspectionRules;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Scheduling\SchedulingTestCase;

class HydrantInspectionHistoryTest extends SchedulingTestCase
{
    private function createHydrant(Unit $unit, array $attributes = []): Hydrant
    {
        return Hydrant::query()->create(array_merge([
            'unit_id' => $unit->id,
            'hydrant_code' => 'HYD-'.strtoupper(Str::random(6)),
            'name' => 'Hydrant Utama',
            'hydrant_type' => 'hydrant_box',
            'brand' => 'BrandX',
            'model' => 'ModelX',
            'serial_number' => 'SN-001',
            'procurement_date' => null,
            'installation_date' => null,
            'last_maintenance_date' => '2026-10-01',
            'placement' => 'Lobby',
            'qr_code' => (string) Str::uuid(),
            'photo' => null,
            'is_active' => true,
        ], $attributes));
    }

    private function createInspection(Hydrant $hydrant, Security $security, array $attributes = [], ?string $masterBrand = null): HydrantInspection
    {
        $unit = $hydrant->unit ?? Unit::find($hydrant->unit_id);
        $inspectedAt = $attributes['inspected_at'] ?? '2026-11-16 10:00:00';
        $result = $attributes['result'] ?? 'ok';
        $notes = array_key_exists('notes', $attributes) ? $attributes['notes'] : null;

        $inspection = HydrantInspection::query()->create([
            'hydrant_id' => $hydrant->id,
            'unit_id' => $hydrant->unit_id,
            'security_id' => $security->id,
            'inspected_at' => $inspectedAt,
            'result' => $result,
            'notes' => $notes,
            'unit_name_snapshot' => $unit->unit_name,
            'unit_code_snapshot' => $unit->unit_code,
            'hydrant_code_snapshot' => $hydrant->hydrant_code,
            'name_snapshot' => $hydrant->name,
            'hydrant_type_snapshot' => $hydrant->hydrant_type,
            'brand_snapshot' => $attributes['brand_snapshot'] ?? ($masterBrand ?? $hydrant->brand),
            'model_snapshot' => $hydrant->model,
            'serial_number_snapshot' => $hydrant->serial_number,
            'placement_snapshot' => $hydrant->placement,
            'last_maintenance_date_snapshot' => $hydrant->last_maintenance_date,
        ]);

        foreach (AssetInspectionRules::hydrantChecklist() as $idx => $def) {
            $isGood = $result === 'ok' ? true : ($idx !== 0);
            HydrantInspectionItem::query()->create([
                'hydrant_inspection_id' => $inspection->id,
                'item_name' => $def['item_name'],
                'item_label' => $def['item_label'],
                'is_good' => $isGood,
                'note' => $isGood ? null : 'Catatan item',
                'sort_order' => $def['sort_order'],
            ]);
        }

        return $inspection->fresh();
    }

    private function attachPhoto(HydrantInspection $inspection, string $relativePath = 'asset-inspections/hydrant/1/photo.jpg'): HydrantInspectionPhoto
    {
        return HydrantInspectionPhoto::query()->create([
            'hydrant_inspection_id' => $inspection->id,
            'photo_path' => $relativePath,
            'caption' => null,
        ]);
    }

    public function test_security_can_view_history_for_own_unit(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit, 'Petugas A');
        $hydrant = $this->createHydrant($unit, ['hydrant_code' => 'HYD-HIST']);
        $this->createInspection($hydrant, $security, ['inspected_at' => '2026-11-16 09:00:00', 'result' => 'ok']);

        $response = $this->actingAs($security->user)->get("/app/hydrant/{$hydrant->id}/inspections");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('App/Hydrant/Inspections')
            ->where('hydrant.id', $hydrant->id)
            ->has('items', 1)
            ->where('items.0.hydrant_code', 'HYD-HIST')
            ->where('items.0.officer_name', 'Petugas A')
            ->where('items.0.result', 'ok')
            ->where('items.0.result_label', 'Baik')
        );
    }

    public function test_security_cannot_access_history_of_other_unit_hydrant(): void
    {
        $unit = $this->createUnit();
        $other = $this->createUnit();
        $security = $this->createSecurity($unit);
        $otherSecurity = $this->createSecurity($other, 'Petugas B');
        $hydrant = $this->createHydrant($other, ['hydrant_code' => 'HYD-OTH']);

        $this->actingAs($security->user)->get("/app/hydrant/{$hydrant->id}/inspections")->assertNotFound();

        $inspection = $this->createInspection($hydrant, $otherSecurity);
        $this->actingAs($security->user)->get("/app/hydrant/inspections/{$inspection->id}")->assertNotFound();
    }

    public function test_admin_can_only_view_history_within_unit_scope(): void
    {
        $root = $this->createUnit();
        $child = $this->createUnit('ulp', $root);
        $other = $this->createUnit();
        $admin = $this->createAdmin($child);

        $secChild = $this->createSecurity($child, 'Sec Child');
        $secOther = $this->createSecurity($other, 'Sec Other');
        $hydrantChild = $this->createHydrant($child, ['hydrant_code' => 'HYD-CH']);
        $hydrantOther = $this->createHydrant($other, ['hydrant_code' => 'HYD-OT']);
        $inspChild = $this->createInspection($hydrantChild, $secChild);
        $inspOther = $this->createInspection($hydrantOther, $secOther);

        $own = $this->actingAs($admin)->get("/admin/hydrant/{$hydrantChild->id}/inspections");
        $own->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Hydrant/Inspections')
            ->has('items', 1)
            ->where('items.0.id', $inspChild->id)
        );

        $this->actingAs($admin)->get("/admin/hydrant/{$hydrantOther->id}/inspections")->assertForbidden();
        $this->actingAs($admin)->get("/admin/hydrant/inspections/{$inspOther->id}")->assertForbidden();
    }

    public function test_superadmin_can_view_history_across_units(): void
    {
        $u1 = $this->createUnit();
        $u2 = $this->createUnit();
        $super = User::factory()->create(['role' => 'superadmin', 'is_active' => true, 'unit_id' => $u1->id]);
        $s1 = $this->createSecurity($u1, 'S1');
        $s2 = $this->createSecurity($u2, 'S2');
        $h1 = $this->createHydrant($u1, ['hydrant_code' => 'HYD-U1']);
        $h2 = $this->createHydrant($u2, ['hydrant_code' => 'HYD-U2']);
        $i1 = $this->createInspection($h1, $s1);
        $i2 = $this->createInspection($h2, $s2);

        $this->actingAs($super)->get("/admin/hydrant/{$h1->id}/inspections")->assertOk();
        $this->actingAs($super)->get("/admin/hydrant/{$h2->id}/inspections")->assertOk();
        $this->actingAs($super)->get("/admin/hydrant/inspections/{$i1->id}")->assertOk();
        $this->actingAs($super)->get("/admin/hydrant/inspections/{$i2->id}")->assertOk();
    }

    public function test_inactive_security_is_rejected_from_history(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $hydrant = $this->createHydrant($unit);
        $security->user->update(['is_active' => false]);

        $this->actingAs($security->user)->get("/app/hydrant/{$hydrant->id}/inspections")->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_security_without_profile_is_rejected_from_history(): void
    {
        $unit = $this->createUnit();
        $user = User::factory()->create([
            'role' => 'security',
            'is_active' => true,
            'unit_id' => $unit->id,
        ]);
        $hydrant = $this->createHydrant($unit);

        $this->actingAs($user)->get("/app/hydrant/{$hydrant->id}/inspections")->assertForbidden();
    }

    public function test_detail_shows_all_checklist_items(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $hydrant = $this->createHydrant($unit);
        $inspection = $this->createInspection($hydrant, $security);

        $response = $this->actingAs($security->user)->get("/app/hydrant/inspections/{$inspection->id}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('App/Hydrant/InspectionShow')
            ->where('inspection.id', $inspection->id)
            ->has('inspection.items', 11)
            ->where('inspection.items.0.item_name', 'kondisi_baterai')
            ->where('inspection.items.10.item_name', 'pipa_sambungan')
        );
    }

    public function test_detail_uses_snapshot_historical_data_not_current_master(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $hydrant = $this->createHydrant($unit, ['hydrant_code' => 'HYD-SNAP', 'brand' => 'NEW-BRAND']);
        $inspection = $this->createInspection($hydrant, $security, ['brand_snapshot' => 'OLD-BRAND']);

        $hydrant->update(['brand' => 'CHANGED-BRAND', 'placement' => 'CHANGED-LOC']);

        $response = $this->actingAs($security->user)->get("/app/hydrant/inspections/{$inspection->id}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('App/Hydrant/InspectionShow')
            ->where('inspection.snapshot.hydrant_code', 'HYD-SNAP')
            ->where('inspection.snapshot.brand', 'OLD-BRAND')
            ->where('inspection.snapshot.placement', 'Lobby')
        );
    }

    public function test_multiple_inspections_in_same_month_shown_as_separate_records_in_chronological_list(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $hydrant = $this->createHydrant($unit);
        $i1 = $this->createInspection($hydrant, $security, ['inspected_at' => '2026-11-05 08:00:00']);
        $i2 = $this->createInspection($hydrant, $security, ['inspected_at' => '2026-11-20 09:00:00']);
        $i3 = $this->createInspection($hydrant, $security, ['inspected_at' => '2026-11-20 15:00:00']);

        $response = $this->actingAs($security->user)->get("/app/hydrant/{$hydrant->id}/inspections");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('App/Hydrant/Inspections')
            ->has('items', 3)
            ->where('items.0.id', $i3->id)
            ->where('items.1.id', $i2->id)
            ->where('items.2.id', $i1->id)
        );
    }

    public function test_authorized_user_can_access_inspection_photo(): void
    {
        Storage::fake('local');
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $hydrant = $this->createHydrant($unit);
        $inspection = $this->createInspection($hydrant, $security);

        $path = AssetInspectionPhotoStorage::directory('hydrant', (int) $hydrant->id).'/'.Str::uuid().'.jpg';
        Storage::disk('local')->put($path, 'fake-image-bytes');
        $photo = $this->attachPhoto($inspection, $path);

        $response = $this->actingAs($security->user)->get("/app/hydrant/inspection-photos/{$photo->id}");

        $response->assertOk();
        $this->assertSame('fake-image-bytes', $response->streamedContent());
    }

    public function test_unauthorized_user_cannot_access_inspection_photo(): void
    {
        Storage::fake('local');
        $unit = $this->createUnit();
        $other = $this->createUnit();
        $security = $this->createSecurity($unit);
        $otherSecurity = $this->createSecurity($other);
        $hydrant = $this->createHydrant($other);
        $inspection = $this->createInspection($hydrant, $otherSecurity);

        $path = AssetInspectionPhotoStorage::directory('hydrant', (int) $hydrant->id).'/'.Str::uuid().'.jpg';
        Storage::disk('local')->put($path, 'secret');
        $photo = $this->attachPhoto($inspection, $path);

        $this->actingAs($security->user)->get("/app/hydrant/inspection-photos/{$photo->id}")->assertNotFound();

        $admin = $this->createAdmin($unit);
        $this->actingAs($admin)->get("/admin/hydrant/inspection-photos/{$photo->id}")->assertNotFound();
    }

    public function test_photo_endpoint_rejects_arbitrary_or_unmanaged_paths(): void
    {
        Storage::fake('local');
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $hydrant = $this->createHydrant($unit);
        $inspection = $this->createInspection($hydrant, $security);

        Storage::disk('local')->put('secrets/config.txt', 'top-secret');
        $photo = $this->attachPhoto($inspection, 'secrets/config.txt');

        $this->actingAs($security->user)->get("/app/hydrant/inspection-photos/{$photo->id}")->assertNotFound();

        $admin = User::factory()->create(['role' => 'superadmin', 'is_active' => true, 'unit_id' => $unit->id]);
        $this->actingAs($admin)->get("/admin/hydrant/inspection-photos/{$photo->id}")->assertNotFound();
    }

    public function test_history_remains_accessible_when_master_hydrant_is_deactivated(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $hydrant = $this->createHydrant($unit, ['is_active' => false]);
        $inspection = $this->createInspection($hydrant, $security);

        $this->actingAs($security->user)->get("/app/hydrant/{$hydrant->id}/inspections")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('items', 1)->where('items.0.id', $inspection->id));
        $this->actingAs($security->user)->get("/app/hydrant/inspections/{$inspection->id}")->assertOk();
    }

    public function test_no_edit_or_delete_history_endpoints_are_available(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $hydrant = $this->createHydrant($unit);
        $inspection = $this->createInspection($hydrant, $security);

        $this->actingAs($security->user)->put("/app/hydrant/inspections/{$inspection->id}", [])->assertStatus(405);
        $this->actingAs($security->user)->delete("/app/hydrant/inspections/{$inspection->id}")->assertStatus(405);
        $admin = User::factory()->create(['role' => 'superadmin', 'is_active' => true, 'unit_id' => $unit->id]);
        $this->actingAs($admin)->put("/admin/hydrant/inspections/{$inspection->id}", [])->assertStatus(405);
        $this->actingAs($admin)->delete("/admin/hydrant/inspections/{$inspection->id}")->assertStatus(405);
    }

    public function test_history_filters_by_result_search_and_date_range(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit, 'Alpha Officer');
        $hydrant = $this->createHydrant($unit, ['hydrant_code' => 'HYD-FLT']);
        $ok = $this->createInspection($hydrant, $security, ['inspected_at' => '2026-11-10 08:00:00', 'result' => 'ok']);
        $bad = $this->createInspection($hydrant, $security, ['inspected_at' => '2026-11-20 08:00:00', 'result' => 'not_ok', 'notes' => 'x']);

        $byResult = $this->actingAs($security->user)->get("/app/hydrant/{$hydrant->id}/inspections?result=not_ok");
        $byResult->assertOk()->assertInertia(fn (Assert $page) => $page->has('items', 1)->where('items.0.id', $bad->id));

        $bySearch = $this->actingAs($security->user)->get("/app/hydrant/{$hydrant->id}/inspections?search=Alpha");
        $bySearch->assertOk()->assertInertia(fn (Assert $page) => $page->has('items', 2));

        $byDate = $this->actingAs($security->user)->get("/app/hydrant/{$hydrant->id}/inspections?date_from=2026-11-15&date_to=2026-11-30");
        $byDate->assertOk()->assertInertia(fn (Assert $page) => $page->has('items', 1)->where('items.0.id', $bad->id));
    }
}
