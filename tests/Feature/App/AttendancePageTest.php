<?php

namespace Tests\Feature\App;

use App\Models\Security;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AttendancePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_user_can_open_attendance_page(): void
    {
        $security = Security::factory()->create();

        $this->actingAs($security->user)
            ->get('/app/attendance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('App/Attendance/Index')
            );
    }

    public function test_security_user_without_profile_is_forbidden(): void
    {
        $user = User::factory()->create(['role' => 'security']);

        $this->actingAs($user)
            ->get('/app/attendance')
            ->assertForbidden();
    }

    public function test_non_security_user_is_forbidden(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get('/app/attendance')
            ->assertForbidden();
    }
}
