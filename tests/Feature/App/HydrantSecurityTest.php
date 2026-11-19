<?php

namespace Tests\Feature\App;

use App\Models\Hydrant;
use App\Models\HydrantInspection;
use App\Models\Security;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Scheduling\SchedulingTestCase;

class HydrantSecurityTest extends SchedulingTestCase
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
            'serial_number' => null,
            'procurement_date' => null,
            'installation_date' => null,
            'last_maintenance_date' => null,
            'placement' => 'Lobby',
            'qr_code' => (string) Str::uuid(),
            'photo' => null,
            'is_active' => true,
        ], $attributes));
    }

    public function test_active_security_can_view_hydrant_list_for_own_unit(): void
    {
        $unit = $this->createUnit();
        $other = $this->createUnit();
        $security = $this->createSecurity($unit, 'Satpam A');
        $own = $this->createHydrant($unit, [
            'hydrant_code' => 'HYD-OWN',
            'name' => 'Hydrant Lobby',
            'hydrant_type' => 'fire_pump_diesel',
            'brand' => 'OwnBrand',
            'model' => 'OwnModel',
            'placement' => 'Pos A',
        ]);
        $this->createHydrant($other, ['hydrant_code' => 'HYD-OTH']);

        $response = $this->actingAs($security->user)->get('/app/hydrant');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('App/Hydrant/Index')
            ->has('items', 1)
            ->where('items.0.id', $own->id)
            ->where('items.0.hydrant_code', 'HYD-OWN')
            ->where('items.0.name', 'Hydrant Lobby')
            ->where('items.0.hydrant_type_label', 'Fire Pump Diesel')
            ->where('items.0.brand', 'OwnBrand')
            ->where('items.0.model', 'OwnModel')
            ->where('items.0.placement', 'Pos A')
            ->where('items.0.is_active', true)
        );
    }

    public function test_security_cannot_see_hydrant_from_other_unit(): void
    {
        $unit = $this->createUnit();
        $other = $this->createUnit();
        $security = $this->createSecurity($unit);
        $this->createHydrant($unit, ['hydrant_code' => 'HYD-OWN']);
        $this->createHydrant($other, ['hydrant_code' => 'HYD-OTH']);

        $response = $this->actingAs($security->user)->get('/app/hydrant');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('App/Hydrant/Index')
            ->has('items', 1)
            ->where('items.0.hydrant_code', 'HYD-OWN')
        );
    }

    public function test_security_cannot_open_detail_of_hydrant_from_other_unit(): void
    {
        $unit = $this->createUnit();
        $other = $this->createUnit();
        $security = $this->createSecurity($unit);
        $foreign = $this->createHydrant($other, ['hydrant_code' => 'HYD-OTH']);

        $this->actingAs($security->user)
            ->get('/app/hydrant/'.$foreign->id)
            ->assertNotFound();
    }

    public function test_inactive_security_account_is_rejected(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $security->user->update(['is_active' => false]);

        $this->actingAs($security->user)
            ->get('/app/hydrant')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_security_without_profile_is_rejected(): void
    {
        $unit = $this->createUnit();
        $user = User::factory()->create([
            'role' => 'security',
            'is_active' => true,
            'unit_id' => $unit->id,
        ]);

        $this->actingAs($user)
            ->get('/app/hydrant')
            ->assertForbidden();
    }

    public function test_security_without_unit_is_rejected(): void
    {
        $user = User::factory()->create([
            'role' => 'security',
            'is_active' => true,
            'unit_id' => null,
        ]);
        Security::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Satpam No Unit',
            'kta_issued_date' => '2026-01-01',
        ]);

        $this->actingAs($user)
            ->get('/app/hydrant')
            ->assertForbidden();
    }

    public function test_admin_and_superadmin_are_denied_from_security_hydrant_portal(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $hydrant = $this->createHydrant($unit, ['hydrant_code' => 'HYD-ADMIN-DENY']);

        foreach ([$admin, $superadmin] as $user) {
            $this->actingAs($user)->get('/app/hydrant')->assertForbidden();
            $this->actingAs($user)->get('/app/hydrant/'.$hydrant->id)->assertForbidden();
        }
    }

    public function test_inactive_hydrant_is_not_shown_as_viewable_asset(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $active = $this->createHydrant($unit, ['hydrant_code' => 'HYD-ACT', 'is_active' => true]);
        $inactive = $this->createHydrant($unit, ['hydrant_code' => 'HYD-OFF', 'is_active' => false]);

        $list = $this->actingAs($security->user)->get('/app/hydrant');
        $list->assertOk();
        $list->assertInertia(fn (Assert $page) => $page
            ->component('App/Hydrant/Index')
            ->has('items', 1)
            ->where('items.0.id', $active->id)
        );

        $this->actingAs($security->user)
            ->get('/app/hydrant/'.$inactive->id)
            ->assertNotFound();
    }

    public function test_hydrant_search_works(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $this->createHydrant($unit, ['hydrant_code' => 'HYD-ALPHA', 'name' => 'Hydrant Alpha', 'brand' => 'BrandA', 'model' => 'ModelA', 'placement' => 'Lobby']);
        $match = $this->createHydrant($unit, ['hydrant_code' => 'HYD-BETA', 'name' => 'Hydrant Beta', 'brand' => 'SpecialX', 'model' => 'SpecialModel', 'placement' => 'Gudang']);

        $byCode = $this->actingAs($security->user)->get('/app/hydrant?search=BETA');
        $byCode->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)
            ->where('items.0.id', $match->id)
        );

        $byName = $this->actingAs($security->user)->get('/app/hydrant?search=Beta');
        $byName->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)
            ->where('items.0.id', $match->id)
        );

        $byBrand = $this->actingAs($security->user)->get('/app/hydrant?search=SpecialX');
        $byBrand->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)
            ->where('items.0.id', $match->id)
        );

        $byModel = $this->actingAs($security->user)->get('/app/hydrant?search=SpecialModel');
        $byModel->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)
            ->where('items.0.id', $match->id)
        );

        $byPlacement = $this->actingAs($security->user)->get('/app/hydrant?search=Gudang');
        $byPlacement->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)
            ->where('items.0.id', $match->id)
        );
    }

    public function test_pagination_works_when_data_exceeds_limit(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        for ($i = 1; $i <= 25; $i++) {
            $this->createHydrant($unit, ['hydrant_code' => sprintf('HYD-%03d', $i)]);
        }

        $page1 = $this->actingAs($security->user)->get('/app/hydrant');
        $page1->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('App/Hydrant/Index')
            ->has('items', 20)
            ->where('meta.current_page', 1)
            ->where('meta.last_page', 2)
            ->where('meta.total', 25)
        );

        $page2 = $this->actingAs($security->user)->get('/app/hydrant?page=2');
        $page2->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('items', 5)
            ->where('meta.current_page', 2)
        );
    }

    public function test_detail_shows_hydrant_information(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $hydrant = $this->createHydrant($unit, [
            'hydrant_code' => 'HYD-DETAIL',
            'name' => 'Hydrant Detail',
            'hydrant_type' => 'siamese_connection',
            'brand' => 'DetailBrand',
            'model' => 'DetailModel',
            'serial_number' => 'SN-001',
            'procurement_date' => '2025-01-10',
            'installation_date' => '2025-02-15',
            'last_maintenance_date' => '2026-03-20',
            'placement' => 'Area Parkir',
        ]);

        $response = $this->actingAs($security->user)->get('/app/hydrant/'.$hydrant->id);

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('App/Hydrant/Show')
            ->where('hydrant.id', $hydrant->id)
            ->where('hydrant.hydrant_code', 'HYD-DETAIL')
            ->where('hydrant.name', 'Hydrant Detail')
            ->where('hydrant.hydrant_type_label', 'Siamese Connection')
            ->where('hydrant.brand', 'DetailBrand')
            ->where('hydrant.model', 'DetailModel')
            ->where('hydrant.serial_number', 'SN-001')
            ->where('hydrant.procurement_date', '2025-01-10')
            ->where('hydrant.installation_date', '2025-02-15')
            ->where('hydrant.last_maintenance_date', '2026-03-20')
            ->where('hydrant.placement', 'Area Parkir')
            ->where('hydrant.photo_url', null)
            ->where('latestInspection', null)
        );
    }

    public function test_latest_inspection_info_is_shown_when_available(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit, 'Petugas Jaga');
        $hydrant = $this->createHydrant($unit, ['hydrant_code' => 'HYD-INSP']);

        HydrantInspection::query()->create([
            'hydrant_id' => $hydrant->id,
            'unit_id' => $unit->id,
            'security_id' => $security->id,
            'inspected_at' => '2026-11-10 08:30:00',
            'result' => 'ok',
            'notes' => null,
            'unit_name_snapshot' => $unit->unit_name,
            'unit_code_snapshot' => $unit->unit_code,
            'hydrant_code_snapshot' => $hydrant->hydrant_code,
            'name_snapshot' => $hydrant->name,
            'hydrant_type_snapshot' => $hydrant->hydrant_type,
            'brand_snapshot' => $hydrant->brand,
            'model_snapshot' => $hydrant->model,
            'serial_number_snapshot' => $hydrant->serial_number,
            'placement_snapshot' => $hydrant->placement,
            'last_maintenance_date_snapshot' => $hydrant->last_maintenance_date,
        ]);

        $response = $this->actingAs($security->user)->get('/app/hydrant/'.$hydrant->id);

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('App/Hydrant/Show')
            ->where('hydrant.id', $hydrant->id)
            ->where('hydrant.hydrant_code', 'HYD-INSP')
            ->where('latestInspection.result', 'ok')
            ->where('latestInspection.result_label', 'Baik')
            ->where('latestInspection.officer_name', 'Petugas Jaga')
            ->where('latestInspection.inspected_at', fn ($v) => is_string($v) && $v !== '')
        );
    }
}
