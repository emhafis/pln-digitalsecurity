<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Tests\Feature\Scheduling\SchedulingTestCase;

class AreaAccessTest extends SchedulingTestCase
{
    public function test_guest_is_redirected_to_login_for_both_areas(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->get('/app')->assertRedirect('/login');
    }

    public function test_security_cannot_open_admin_area(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);

        $this->actingAs($security->user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_admin_cannot_open_app_area(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get('/app')
            ->assertForbidden();
    }

    public function test_security_without_profile_cannot_open_app_area(): void
    {
        $unit = $this->createUnit();
        $user = User::factory()->create([
            'role' => 'security',
            'unit_id' => $unit->id,
        ]);

        $this->actingAs($user)
            ->get('/app')
            ->assertForbidden();
    }

    public function test_admin_and_superadmin_can_open_admin_area(): void
    {
        $admin = $this->createAdmin();
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk();

        $this->actingAs($superadmin)
            ->get('/admin')
            ->assertOk();
    }

    public function test_security_and_admin_cannot_open_superadmin_attendance_settings(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $admin = $this->createAdmin($unit);
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $this->actingAs($security->user)
            ->get('/admin/attendance-setting')
            ->assertForbidden();

        $this->actingAs($admin)
            ->get('/admin/attendance-setting')
            ->assertForbidden();

        $this->actingAs($superadmin)
            ->get('/admin/attendance-setting')
            ->assertOk();
    }

    public function test_previously_unprotected_admin_pages_now_require_admin_role(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $admin = $this->createAdmin($unit);

        foreach ([
            '/admin/security-post',
            '/admin/patrol-point',
            '/admin/security',
            '/admin/unit',
            '/admin/user',
        ] as $uri) {
            $this->actingAs($security->user)->get($uri)->assertForbidden();
            $this->actingAs($admin)->get($uri)->assertOk();
        }
    }

    public function test_only_superadmin_can_open_unit_create_form(): void
    {
        $admin = $this->createAdmin();
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $this->actingAs($admin)
            ->get('/admin/unit/create')
            ->assertForbidden();

        $this->actingAs($superadmin)
            ->get('/admin/unit/create')
            ->assertOk();
    }
}
