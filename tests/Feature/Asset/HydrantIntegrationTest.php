<?php

namespace Tests\Feature\Asset;

use App\Models\Hydrant;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Scheduling\SchedulingTestCase;

class HydrantIntegrationTest extends SchedulingTestCase
{
    public function test_hydrant_route_names_are_registered_for_admin_and_security(): void
    {
        foreach ([
            'admin.hydrant.index',
            'admin.hydrant.create',
            'admin.hydrant.store',
            'admin.hydrant.show',
            'admin.hydrant.edit',
            'admin.hydrant.update',
            'admin.hydrant.toggle-active',
            'admin.hydrant.qr-pdf',
            'admin.hydrant.inspections.index',
            'admin.hydrant.inspections.export',
            'admin.hydrant-inspections.show',
            'admin.hydrant-inspections.photo',
            'app.hydrant.index',
            'app.hydrant.show',
            'app.hydrant.inspect.create',
            'app.hydrant.inspect.store',
            'app.hydrant.qr-pdf',
            'app.hydrant.inspections.index',
            'app.hydrant.inspections.show',
            'app.hydrant.inspections.photo',
        ] as $name) {
            $this->assertTrue(Route::has($name), "Route [{$name}] tidak terdaftar.");
        }
    }

    public function test_security_home_and_hydrant_menu_are_accessible_to_security_only(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $hydrant = Hydrant::query()->create([
            'unit_id' => $unit->id,
            'hydrant_code' => 'HYD-INT',
            'name' => 'Hydrant Integrasi',
            'hydrant_type' => 'hydrant_box',
            'brand' => 'BrandX',
            'model' => 'ModelX',
            'serial_number' => null,
            'placement' => 'Lobby',
            'qr_code' => (string) Str::uuid(),
            'is_active' => true,
        ]);

        $this->actingAs($security->user)
            ->get('/app')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('App/Home'));

        $this->actingAs($security->user)
            ->get('/app/hydrant')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('App/Hydrant/Index'));

        $this->actingAs($security->user)
            ->get('/app/hydrant/'.$hydrant->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('App/Hydrant/Show'));

        $this->actingAs($security->user)->get('/admin/hydrant')->assertForbidden();
    }

    public function test_admin_and_superadmin_can_open_hydrant_portal_but_security_portal_is_forbidden(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);
        $superadmin = \App\Models\User::factory()->create(['role' => 'superadmin']);

        foreach ([$admin, $superadmin] as $user) {
            $this->actingAs($user)
                ->get('/admin/hydrant')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('Admin/Hydrant/Index'));

            $this->actingAs($user)->get('/app/hydrant')->assertForbidden();
        }
    }
}
