<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Tests\Feature\Scheduling\SchedulingTestCase;

class AccountStatusTest extends SchedulingTestCase
{
    public function test_deactivated_admin_session_is_terminated(): void
    {
        $admin = $this->createAdmin();
        $admin->update(['is_active' => false]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_deactivated_security_session_is_terminated(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $security->user->update(['is_active' => false]);

        $this->actingAs($security->user)
            ->get('/app')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_deactivated_user_json_request_is_forbidden(): void
    {
        $admin = $this->createAdmin();
        $admin->update(['is_active' => false]);

        $this->actingAs($admin)
            ->getJson('/admin')
            ->assertForbidden();

        $this->assertGuest();
    }

    public function test_inactive_user_is_logged_out_before_login_page_is_shown(): void
    {
        $admin = $this->createAdmin();
        $admin->update(['is_active' => false]);

        $this->actingAs($admin)
            ->get('/login')
            ->assertOk();

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => false,
        ]);

        $this->post('/login', [
            'username' => $admin->username,
            'password' => 'password',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_active_admin_can_open_admin_area(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk();
    }

    public function test_active_security_with_profile_can_open_app_area(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);

        $this->actingAs($security->user)
            ->get('/app')
            ->assertOk();
    }
}
