<?php

namespace Tests\Feature\Asset;

use App\Models\Apar;
use App\Models\AparInspection;
use App\Models\AparInspectionItem;
use App\Models\AparInspectionPhoto;
use App\Models\Security;
use App\Models\Unit;
use App\Models\User;
use App\Support\AssetInspectionPhotoStorage;
use App\Support\AssetInspectionRules;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Scheduling\SchedulingTestCase;

class AparInspectionHistoryTest extends SchedulingTestCase
{
    private function createApar(Unit $unit, array $attributes = []): Apar
    {
        return Apar::query()->create(array_merge([
            'unit_id' => $unit->id,
            'apar_code' => 'APR-' . strtoupper(Str::random(6)),
            'apar_type' => 'co2',
            'brand' => 'BrandX',
            'capacity' => '6 Kg',
            'serial_number' => 'SN-001',
            'placement' => 'Lobby',
            'expiry_date' => '2027-01-01',
            'qr_code' => (string) Str::uuid(),
            'is_active' => true,
        ], $attributes));
    }

    private function createInspection(Apar $apar, Security $security, array $attributes = [], ?string $masterBrand = null): AparInspection
    {
        $unit = $apar->unit ?? Unit::find($apar->unit_id);
        $inspectedAt = $attributes['inspected_at'] ?? '2026-11-16 10:00:00';
        $result = $attributes['result'] ?? 'ok';
        $notes = array_key_exists('notes', $attributes) ? $attributes['notes'] : null;

        $inspection = AparInspection::query()->create([
            'apar_id' => $apar->id,
            'unit_id' => $apar->unit_id,
            'security_id' => $security->id,
            'inspected_at' => $inspectedAt,
            'result' => $result,
            'notes' => $notes,
            'unit_name_snapshot' => $unit->unit_name,
            'unit_code_snapshot' => $unit->unit_code,
            'apar_code_snapshot' => $apar->apar_code,
            'apar_type_snapshot' => $apar->apar_type,
            'brand_snapshot' => $attributes['brand_snapshot'] ?? ($masterBrand ?? $apar->brand),
            'capacity_snapshot' => $apar->capacity,
            'serial_number_snapshot' => $apar->serial_number,
            'placement_snapshot' => $apar->placement,
            'apar_expiry_date_snapshot' => $apar->expiry_date,
        ]);

        foreach (AssetInspectionRules::aparChecklist() as $idx => $def) {
            $isGood = $result === 'ok' ? true : ($idx !== 0);
            AparInspectionItem::query()->create([
                'apar_inspection_id' => $inspection->id,
                'item_name' => $def['item_name'],
                'item_label' => $def['item_label'],
                'is_good' => $isGood,
                'note' => $isGood ? null : 'Catatan item',
                'sort_order' => $def['sort_order'],
            ]);
        }

        return $inspection->fresh();
    }

    private function attachPhoto(AparInspection $inspection, string $relativePath = 'asset-inspections/apar/1/photo.jpg'): AparInspectionPhoto
    {
        return AparInspectionPhoto::query()->create([
            'apar_inspection_id' => $inspection->id,
            'photo_path' => $relativePath,
            'caption' => null,
        ]);
    }

    public function test_security_can_view_history_for_own_unit(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit, 'Petugas A');
        $apar = $this->createApar($unit, ['apar_code' => 'APR-HIST']);
        $this->createInspection($apar, $security, ['inspected_at' => '2026-11-16 09:00:00', 'result' => 'ok']);

        $response = $this->actingAs($security->user)->get("/app/apar/{$apar->id}/inspections");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('App/Apar/Inspections')
            ->where('apar.id', $apar->id)
            ->has('items', 1)
            ->where('items.0.apar_code', 'APR-HIST')
            ->where('items.0.officer_name', 'Petugas A')
            ->where('items.0.result', 'ok')
            ->where('items.0.result_label', 'Baik')
        );
    }

    public function test_security_cannot_access_history_of_other_unit_apar(): void
    {
        $unit = $this->createUnit();
        $other = $this->createUnit();
        $security = $this->createSecurity($unit);
        $otherSecurity = $this->createSecurity($other, 'Petugas B');
        $apar = $this->createApar($other, ['apar_code' => 'APR-OTH']);

        $this->actingAs($security->user)->get("/app/apar/{$apar->id}/inspections")->assertNotFound();

        $inspection = $this->createInspection($apar, $otherSecurity);
        $this->actingAs($security->user)->get("/app/apar/inspections/{$inspection->id}")->assertNotFound();
    }

    public function test_admin_can_only_view_history_within_unit_scope(): void
    {
        $root = $this->createUnit();
        $child = $this->createUnit('ulp', $root);
        $other = $this->createUnit();
        $admin = $this->createAdmin($child);

        $secChild = $this->createSecurity($child, 'Sec Child');
        $secOther = $this->createSecurity($other, 'Sec Other');
        $aparChild = $this->createApar($child, ['apar_code' => 'APR-CH']);
        $aparOther = $this->createApar($other, ['apar_code' => 'APR-OT']);
        $inspChild = $this->createInspection($aparChild, $secChild);
        $inspOther = $this->createInspection($aparOther, $secOther);

        $own = $this->actingAs($admin)->get("/admin/apar/{$aparChild->id}/inspections");
        $own->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Apar/Inspections')
            ->has('items', 1)
            ->where('items.0.id', $inspChild->id)
        );

        $this->actingAs($admin)->get("/admin/apar/{$aparOther->id}/inspections")->assertForbidden();
        $this->actingAs($admin)->get("/admin/apar/inspections/{$inspOther->id}")->assertForbidden();
    }

    public function test_superadmin_can_view_history_across_units(): void
    {
        $u1 = $this->createUnit();
        $u2 = $this->createUnit();
        $super = User::factory()->create(['role' => 'superadmin', 'is_active' => true, 'unit_id' => $u1->id]);
        $s1 = $this->createSecurity($u1, 'S1');
        $s2 = $this->createSecurity($u2, 'S2');
        $a1 = $this->createApar($u1, ['apar_code' => 'APR-U1']);
        $a2 = $this->createApar($u2, ['apar_code' => 'APR-U2']);
        $i1 = $this->createInspection($a1, $s1);
        $i2 = $this->createInspection($a2, $s2);

        $this->actingAs($super)->get("/admin/apar/{$a1->id}/inspections")->assertOk();
        $this->actingAs($super)->get("/admin/apar/{$a2->id}/inspections")->assertOk();
        $this->actingAs($super)->get("/admin/apar/inspections/{$i1->id}")->assertOk();
        $this->actingAs($super)->get("/admin/apar/inspections/{$i2->id}")->assertOk();
    }

    public function test_inactive_security_is_rejected_from_history(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $apar = $this->createApar($unit);
        $security->user->update(['is_active' => false]);

        $this->actingAs($security->user)->get("/app/apar/{$apar->id}/inspections")->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_security_without_profile_is_rejected_from_history(): void
    {
        $unit = $this->createUnit();
        $user = \App\Models\User::factory()->create([
            'role' => 'security',
            'is_active' => true,
            'unit_id' => $unit->id,
        ]);
        $apar = $this->createApar($unit);

        $this->actingAs($user)->get("/app/apar/{$apar->id}/inspections")->assertForbidden();
    }

    public function test_detail_shows_all_checklist_items(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $apar = $this->createApar($unit);
        $inspection = $this->createInspection($apar, $security);

        $response = $this->actingAs($security->user)->get("/app/apar/inspections/{$inspection->id}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('App/Apar/InspectionShow')
            ->where('inspection.id', $inspection->id)
            ->has('inspection.items', 10)
            ->where('inspection.items.0.item_name', 'pressure_gauge')
            ->where('inspection.items.9.item_name', 'expiry_date')
        );
    }

    public function test_detail_uses_snapshot_historical_data_not_current_master(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $apar = $this->createApar($unit, ['apar_code' => 'APR-SNAP', 'brand' => 'NEW-BRAND']);
        $inspection = $this->createInspection($apar, $security, ['brand_snapshot' => 'OLD-BRAND']);

        $apar->update(['brand' => 'CHANGED-BRAND', 'placement' => 'CHANGED-LOC']);

        $response = $this->actingAs($security->user)->get("/app/apar/inspections/{$inspection->id}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('App/Apar/InspectionShow')
            ->where('inspection.snapshot.apar_code', 'APR-SNAP')
            ->where('inspection.snapshot.brand', 'OLD-BRAND')
            ->where('inspection.snapshot.placement', 'Lobby')
        );
    }

    public function test_multiple_inspections_in_same_month_shown_as_separate_records_in_chronological_list(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $apar = $this->createApar($unit);
        $i1 = $this->createInspection($apar, $security, ['inspected_at' => '2026-11-05 08:00:00']);
        $i2 = $this->createInspection($apar, $security, ['inspected_at' => '2026-11-20 09:00:00']);
        $i3 = $this->createInspection($apar, $security, ['inspected_at' => '2026-11-20 15:00:00']);

        $response = $this->actingAs($security->user)->get("/app/apar/{$apar->id}/inspections");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('App/Apar/Inspections')
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
        $apar = $this->createApar($unit);
        $inspection = $this->createInspection($apar, $security);

        $path = AssetInspectionPhotoStorage::directory('apar', (int) $apar->id) . '/' . Str::uuid() . '.jpg';
        Storage::disk('local')->put($path, 'fake-image-bytes');
        $photo = $this->attachPhoto($inspection, $path);

        $response = $this->actingAs($security->user)->get("/app/apar/inspection-photos/{$photo->id}");

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
        $apar = $this->createApar($other);
        $inspection = $this->createInspection($apar, $otherSecurity);

        $path = AssetInspectionPhotoStorage::directory('apar', (int) $apar->id) . '/' . Str::uuid() . '.jpg';
        Storage::disk('local')->put($path, 'secret');
        $photo = $this->attachPhoto($inspection, $path);

        $this->actingAs($security->user)->get("/app/apar/inspection-photos/{$photo->id}")->assertNotFound();

        $admin = $this->createAdmin($unit);
        $this->actingAs($admin)->get("/admin/apar/inspection-photos/{$photo->id}")->assertNotFound();
    }

    public function test_photo_endpoint_rejects_arbitrary_or_unmanaged_paths(): void
    {
        Storage::fake('local');
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $apar = $this->createApar($unit);
        $inspection = $this->createInspection($apar, $security);

        Storage::disk('local')->put('secrets/config.txt', 'top-secret');
        $photo = $this->attachPhoto($inspection, 'secrets/config.txt');

        $this->actingAs($security->user)->get("/app/apar/inspection-photos/{$photo->id}")->assertNotFound();

        $admin = User::factory()->create(['role' => 'superadmin', 'is_active' => true, 'unit_id' => $unit->id]);
        $this->actingAs($admin)->get("/admin/apar/inspection-photos/{$photo->id}")->assertNotFound();
    }

    public function test_history_remains_accessible_when_master_apar_is_deactivated(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $apar = $this->createApar($unit, ['is_active' => false]);
        $inspection = $this->createInspection($apar, $security);

        $this->actingAs($security->user)->get("/app/apar/{$apar->id}/inspections")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('items', 1)->where('items.0.id', $inspection->id));
        $this->actingAs($security->user)->get("/app/apar/inspections/{$inspection->id}")->assertOk();
    }

    public function test_no_edit_or_delete_history_endpoints_are_available(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $apar = $this->createApar($unit);
        $inspection = $this->createInspection($apar, $security);

        $this->actingAs($security->user)->put("/app/apar/inspections/{$inspection->id}", [])->assertStatus(405);
        $this->actingAs($security->user)->delete("/app/apar/inspections/{$inspection->id}")->assertStatus(405);
        $admin = User::factory()->create(['role' => 'superadmin', 'is_active' => true, 'unit_id' => $unit->id]);
        $this->actingAs($admin)->put("/admin/apar/inspections/{$inspection->id}", [])->assertStatus(405);
        $this->actingAs($admin)->delete("/admin/apar/inspections/{$inspection->id}")->assertStatus(405);
    }

    public function test_history_filters_by_result_search_and_date_range(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit, 'Alpha Officer');
        $apar = $this->createApar($unit, ['apar_code' => 'APR-FLT']);
        $ok = $this->createInspection($apar, $security, ['inspected_at' => '2026-11-10 08:00:00', 'result' => 'ok']);
        $bad = $this->createInspection($apar, $security, ['inspected_at' => '2026-11-20 08:00:00', 'result' => 'not_ok', 'notes' => 'x']);

        $byResult = $this->actingAs($security->user)->get("/app/apar/{$apar->id}/inspections?result=not_ok");
        $byResult->assertOk()->assertInertia(fn (Assert $page) => $page->has('items', 1)->where('items.0.id', $bad->id));

        $bySearch = $this->actingAs($security->user)->get("/app/apar/{$apar->id}/inspections?search=Alpha");
        $bySearch->assertOk()->assertInertia(fn (Assert $page) => $page->has('items', 2));

        $byDate = $this->actingAs($security->user)->get("/app/apar/{$apar->id}/inspections?date_from=2026-11-15&date_to=2026-11-30");
        $byDate->assertOk()->assertInertia(fn (Assert $page) => $page->has('items', 1)->where('items.0.id', $bad->id));
    }
}



