<?php

namespace Tests\Feature\App;

use App\Models\Apar;
use App\Models\AparInspection;
use App\Models\Security;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Scheduling\SchedulingTestCase;

class AparSecurityTest extends SchedulingTestCase
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

    public function test_active_security_can_view_apar_list_for_own_unit(): void
    {
        $unit = $this->createUnit();
        $other = $this->createUnit();
        $security = $this->createSecurity($unit, 'Satpam A');
        $own = $this->createApar($unit, ['apar_code' => 'APR-OWN', 'brand' => 'OwnBrand', 'placement' => 'Pos A']);
        $this->createApar($other, ['apar_code' => 'APR-OTH', 'brand' => 'OtherBrand', 'placement' => 'Pos B']);

        $response = $this->actingAs($security->user)->get('/app/apar');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('App/Apar/Index')
            ->has('items', 1)
            ->where('items.0.id', $own->id)
            ->where('items.0.apar_code', 'APR-OWN')
            ->where('items.0.apar_type_label', 'CO2')
            ->where('items.0.brand', 'OwnBrand')
            ->where('items.0.capacity', '6 Kg')
            ->where('items.0.placement', 'Pos A')
            ->where('items.0.is_active', true)
        );
    }

    public function test_security_cannot_see_apar_from_other_unit(): void
    {
        $unit = $this->createUnit();
        $other = $this->createUnit();
        $security = $this->createSecurity($unit);
        $this->createApar($unit, ['apar_code' => 'APR-OWN']);
        $this->createApar($other, ['apar_code' => 'APR-OTH']);

        $response = $this->actingAs($security->user)->get('/app/apar');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('App/Apar/Index')
            ->has('items', 1)
            ->where('items.0.apar_code', 'APR-OWN')
        );
    }

    public function test_security_cannot_open_detail_of_apar_from_other_unit(): void
    {
        $unit = $this->createUnit();
        $other = $this->createUnit();
        $security = $this->createSecurity($unit);
        $foreign = $this->createApar($other, ['apar_code' => 'APR-OTH']);

        $this->actingAs($security->user)
            ->get('/app/apar/'.$foreign->id)
            ->assertNotFound();
    }

    public function test_inactive_security_account_is_rejected(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $security->user->update(['is_active' => false]);

        $this->actingAs($security->user)
            ->get('/app/apar')
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
            ->get('/app/apar')
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
            ->get('/app/apar')
            ->assertForbidden();
    }

    public function test_inactive_apar_is_not_shown_as_inspectable_asset(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $active = $this->createApar($unit, ['apar_code' => 'APR-ACT', 'is_active' => true]);
        $inactive = $this->createApar($unit, ['apar_code' => 'APR-OFF', 'is_active' => false]);

        $list = $this->actingAs($security->user)->get('/app/apar');
        $list->assertOk();
        $list->assertInertia(fn (Assert $page) => $page
            ->component('App/Apar/Index')
            ->has('items', 1)
            ->where('items.0.id', $active->id)
        );

        $this->actingAs($security->user)
            ->get('/app/apar/'.$inactive->id)
            ->assertNotFound();
    }

    public function test_apar_search_works(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $this->createApar($unit, ['apar_code' => 'APR-ALPHA', 'brand' => 'BrandA', 'placement' => 'Lobby']);
        $match = $this->createApar($unit, ['apar_code' => 'APR-BETA', 'brand' => 'SpecialX', 'placement' => 'Gudang']);

        $byCode = $this->actingAs($security->user)->get('/app/apar?search=BETA');
        $byCode->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)
            ->where('items.0.id', $match->id)
        );

        $byBrand = $this->actingAs($security->user)->get('/app/apar?search=SpecialX');
        $byBrand->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)
            ->where('items.0.id', $match->id)
        );

        $byPlacement = $this->actingAs($security->user)->get('/app/apar?search=Gudang');
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
            $this->createApar($unit, ['apar_code' => sprintf('APR-%03d', $i)]);
        }

        $page1 = $this->actingAs($security->user)->get('/app/apar');
        $page1->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('App/Apar/Index')
            ->has('items', 20)
            ->where('meta.current_page', 1)
            ->where('meta.last_page', 2)
            ->where('meta.total', 25)
        );

        $page2 = $this->actingAs($security->user)->get('/app/apar?page=2');
        $page2->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('items', 5)
            ->where('meta.current_page', 2)
        );
    }

    public function test_latest_inspection_info_is_shown_when_available(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit, 'Petugas Jaga');
        $apar = $this->createApar($unit, ['apar_code' => 'APR-INSP']);

        AparInspection::query()->create([
            'apar_id' => $apar->id,
            'unit_id' => $unit->id,
            'security_id' => $security->id,
            'inspected_at' => '2026-11-10 08:30:00',
            'result' => 'ok',
            'notes' => null,
            'unit_name_snapshot' => $unit->unit_name,
            'unit_code_snapshot' => $unit->unit_code,
            'apar_code_snapshot' => $apar->apar_code,
            'apar_type_snapshot' => $apar->apar_type,
            'brand_snapshot' => $apar->brand,
            'capacity_snapshot' => $apar->capacity,
            'serial_number_snapshot' => $apar->serial_number,
            'placement_snapshot' => $apar->placement,
            'apar_expiry_date_snapshot' => $apar->expiry_date,
        ]);

        $response = $this->actingAs($security->user)->get('/app/apar/'.$apar->id);

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('App/Apar/Show')
            ->where('apar.id', $apar->id)
            ->where('apar.apar_code', 'APR-INSP')
            ->where('latestInspection.result', 'ok')
            ->where('latestInspection.result_label', 'Baik')
            ->where('latestInspection.officer_name', 'Petugas Jaga')
            ->where('latestInspection.inspected_at', fn ($v) => is_string($v) && $v !== '')
        );
    }
}
