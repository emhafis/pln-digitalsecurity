<?php

namespace Tests\Feature\Admin;

use App\Models\Apar;
use App\Models\AparInspection;
use App\Models\AparInspectionPhoto;
use App\Models\Hydrant;
use App\Models\HydrantInspection;
use App\Models\HydrantInspectionPhoto;
use App\Models\Security;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Scheduling\SchedulingTestCase;

class AssetMonitoringTest extends SchedulingTestCase
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

    private function createAparInspection(Apar $apar, Security $security, array $attributes = []): AparInspection
    {
        return AparInspection::query()->create(array_merge([
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
        ], $attributes));
    }

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

    private function createHydrantInspection(Hydrant $hydrant, Security $security, array $attributes = []): HydrantInspection
    {
        return HydrantInspection::query()->create(array_merge([
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
        ], $attributes));
    }

    public function test_admin_monitoring_is_scoped_and_uses_relative_private_photo_urls(): void
    {
        Storage::fake('local');

        $root = $this->createUnit('uid');
        $child = $this->createUnit('ulp', $root);
        $other = $this->createUnit('ulp');
        $admin = $this->createAdmin($root);

        $childSecurity = $this->createSecurity($child, 'Petugas Dalam');
        $otherSecurity = $this->createSecurity($other, 'Petugas Luar');

        $apar = $this->createApar($child, ['apar_code' => 'APR-IN']);
        $otherApar = $this->createApar($other, ['apar_code' => 'APR-OUT']);
        $aparInspection = $this->createAparInspection($apar, $childSecurity);
        $otherAparInspection = $this->createAparInspection($otherApar, $otherSecurity);

        $hydrant = $this->createHydrant($child, ['hydrant_code' => 'HYD-IN']);
        $otherHydrant = $this->createHydrant($other, ['hydrant_code' => 'HYD-OUT']);
        $hydrantInspection = $this->createHydrantInspection($hydrant, $childSecurity);
        $otherHydrantInspection = $this->createHydrantInspection($otherHydrant, $otherSecurity);

        $aparPhotoPath = 'asset-inspections/apar/'.$apar->id.'/foto.jpg';
        $hydrantPhotoPath = 'asset-inspections/hydrant/'.$hydrant->id.'/foto.jpg';
        Storage::disk('local')->put($aparPhotoPath, 'apar-photo');
        Storage::disk('local')->put($hydrantPhotoPath, 'hydrant-photo');
        $aparPhoto = AparInspectionPhoto::query()->create([
            'apar_inspection_id' => $aparInspection->id,
            'photo_path' => $aparPhotoPath,
            'caption' => 'Foto APAR',
        ]);
        $hydrantPhoto = HydrantInspectionPhoto::query()->create([
            'hydrant_inspection_id' => $hydrantInspection->id,
            'photo_path' => $hydrantPhotoPath,
            'caption' => 'Foto Hydrant',
        ]);

        $this->actingAs($admin)
            ->get('/admin/monitoring/apar')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Monitoring/Apar/Index')
                ->has('items', 1)
                ->where('items.0.apar_code', 'APR-IN')
                ->where('summary.total', 1)
            );

        $this->actingAs($admin)
            ->get("/admin/monitoring/apar/{$aparInspection->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Monitoring/Apar/Show')
                ->where('inspection.id', $aparInspection->id)
                ->where('inspection.photos.0.url', route('admin.apar-inspections.photo', $aparPhoto->id, false))
            );

        $this->actingAs($admin)
            ->get("/admin/monitoring/apar/{$otherAparInspection->id}")
            ->assertForbidden();

        $this->actingAs($admin)
            ->get('/admin/monitoring/hydrant')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Monitoring/Hydrant/Index')
                ->has('items', 1)
                ->where('items.0.hydrant_code', 'HYD-IN')
                ->where('summary.total', 1)
            );

        $this->actingAs($admin)
            ->get("/admin/monitoring/hydrant/{$hydrantInspection->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Monitoring/Hydrant/Show')
                ->where('inspection.id', $hydrantInspection->id)
                ->where('inspection.photos.0.url', route('admin.hydrant-inspections.photo', $hydrantPhoto->id, false))
            );

        $this->actingAs($admin)
            ->get("/admin/monitoring/hydrant/{$otherHydrantInspection->id}")
            ->assertForbidden();
    }

    public function test_monitoring_filters_search_result_unit_and_date(): void
    {
        $unit = $this->createUnit('uid');
        $child = $this->createUnit('ulp', $unit);
        $other = $this->createUnit('ulp');
        $admin = $this->createAdmin($unit);
        $security = $this->createSecurity($unit, 'Petugas Filter');
        $childSecurity = $this->createSecurity($child, 'Petugas Anak');
        $otherSecurity = $this->createSecurity($other, 'Petugas Lain');

        $apar = $this->createApar($unit, ['apar_code' => 'APR-FILTER', 'placement' => 'Gedung A']);
        $childApar = $this->createApar($child, ['apar_code' => 'APR-CHILD']);
        $otherApar = $this->createApar($other, ['apar_code' => 'APR-OTHER']);
        $okInspection = $this->createAparInspection($apar, $security, ['inspected_at' => '2026-11-10 10:00:00']);
        $notOkInspection = $this->createAparInspection($apar, $security, [
            'inspected_at' => '2026-11-12 10:00:00',
            'result' => 'not_ok',
        ]);
        $childInspection = $this->createAparInspection($childApar, $childSecurity, ['inspected_at' => '2026-11-10 10:00:00']);
        $this->createAparInspection($otherApar, $otherSecurity, ['inspected_at' => '2026-11-10 10:00:00']);

        $this->actingAs($admin)
            ->get('/admin/monitoring/apar?search=APR-FILTER&result=ok&date_from=2026-11-09&date_to=2026-11-11')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('items', 1)
                ->where('items.0.id', $okInspection->id)
                ->where('filters.search', 'APR-FILTER')
                ->where('filters.result', 'ok')
                ->where('filters.date_from', '2026-11-09')
                ->where('filters.date_to', '2026-11-11')
            );

        $this->actingAs($admin)
            ->get('/admin/monitoring/apar?result=not_ok')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('items', 1)
                ->where('items.0.id', $notOkInspection->id)
            );

        $this->actingAs($admin)
            ->get('/admin/monitoring/apar?unit_id='.$child->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('items', 1)
                ->where('items.0.id', $childInspection->id)
                ->where('filters.unit_id', $child->id)
            );

        $this->actingAs($admin)
            ->get('/admin/monitoring/apar?unit_id='.$other->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.unit_id', null)
                ->has('items', 3)
            );
    }

    public function test_superadmin_monitoring_sees_all_units(): void
    {
        $unitA = $this->createUnit('ulp');
        $unitB = $this->createUnit('ulp');
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $securityA = $this->createSecurity($unitA, 'Petugas A');
        $securityB = $this->createSecurity($unitB, 'Petugas B');
        $aparA = $this->createApar($unitA, ['apar_code' => 'APR-A']);
        $aparB = $this->createApar($unitB, ['apar_code' => 'APR-B']);
        $this->createAparInspection($aparA, $securityA);
        $this->createAparInspection($aparB, $securityB);

        $hydrantA = $this->createHydrant($unitA, ['hydrant_code' => 'HYD-A']);
        $hydrantB = $this->createHydrant($unitB, ['hydrant_code' => 'HYD-B']);
        $this->createHydrantInspection($hydrantA, $securityA);
        $this->createHydrantInspection($hydrantB, $securityB);

        $this->actingAs($superadmin)
            ->get('/admin/monitoring/apar')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('items', 2));

        $this->actingAs($superadmin)
            ->get('/admin/monitoring/hydrant')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('items', 2));
    }

    public function test_security_is_denied_from_monitoring_pages(): void
    {
        $unit = $this->createUnit('ulp');
        $security = $this->createSecurity($unit);

        $this->actingAs($security->user)->get('/admin/monitoring/apar')->assertForbidden();
        $this->actingAs($security->user)->get('/admin/monitoring/hydrant')->assertForbidden();
    }
}
