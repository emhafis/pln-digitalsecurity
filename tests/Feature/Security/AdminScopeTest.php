<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Scheduling\SchedulingTestCase;

class AdminScopeTest extends SchedulingTestCase
{
    public function test_unit_index_is_limited_to_own_unit_and_descendants(): void
    {
        $root = $this->createUnit('up');
        $root->update(['unit_name' => 'Root Unit']);

        $child = $this->createUnit('ulp', $root);
        $child->update(['unit_name' => 'Child Unit']);

        $other = $this->createUnit('ulp');
        $other->update(['unit_name' => 'Other Unit']);

        $admin = $this->createAdmin($root);

        $this->actingAs($admin)
            ->get('/admin/unit?sort=name&dir=asc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Unit/Index')
                ->has('units', 2)
                ->where('units.0.id', $child->id)
                ->where('units.1.id', $root->id)
            );
    }

    public function test_admin_cannot_view_or_change_units_outside_scope(): void
    {
        $root = $this->createUnit('up');
        $child = $this->createUnit('ulp', $root);
        $other = $this->createUnit('ulp');
        $admin = $this->createAdmin($root);

        $this->actingAs($admin)
            ->get("/admin/unit/{$child->id}")
            ->assertOk();

        $this->actingAs($admin)
            ->get("/admin/unit/{$other->id}")
            ->assertForbidden();

        $this->actingAs($admin)
            ->get("/admin/unit/{$other->id}/edit")
            ->assertForbidden();

        $this->actingAs($admin)
            ->put("/admin/unit/{$other->id}", [
                'unit_name' => 'Hacked Unit',
                'unit_level' => 'ulp',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete("/admin/unit/{$other->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('units', [
            'id' => $other->id,
            'unit_name' => $other->unit_name,
        ]);
    }

    public function test_only_superadmin_can_create_units(): void
    {
        $admin = $this->createAdmin();
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $payload = [
            'unit_name' => 'Unit Baru',
            'unit_level' => 'ulp',
        ];

        $this->actingAs($admin)
            ->post('/admin/unit', $payload)
            ->assertForbidden();

        $this->assertDatabaseMissing('units', ['unit_name' => 'UNIT BARU']);

        $this->actingAs($superadmin)
            ->post('/admin/unit', $payload)
            ->assertRedirect(route('admin.unit.index'));

        $this->assertDatabaseHas('units', ['unit_name' => 'UNIT BARU']);
    }

    public function test_user_index_is_limited_to_scope_and_hides_superadmin(): void
    {
        $root = $this->createUnit('up');
        $child = $this->createUnit('ulp', $root);
        $other = $this->createUnit('ulp');
        $admin = $this->createAdmin($root);

        User::factory()->create([
            'username' => 'a_same',
            'role' => 'security',
            'unit_id' => $root->id,
        ]);
        User::factory()->create([
            'username' => 'b_child',
            'role' => 'security',
            'unit_id' => $child->id,
        ]);
        User::factory()->create([
            'username' => 'c_other',
            'role' => 'security',
            'unit_id' => $other->id,
        ]);
        User::factory()->create([
            'username' => 'd_superadmin',
            'role' => 'superadmin',
            'unit_id' => $root->id,
        ]);

        $this->actingAs($admin)
            ->get('/admin/user?role=security&sort=username&dir=asc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/User/Index')
                ->has('users', 2)
                ->where('users.0.username', 'a_same')
                ->where('users.1.username', 'b_child')
            );
    }

    public function test_admin_cannot_manage_users_outside_scope_or_superadmin(): void
    {
        $root = $this->createUnit('up');
        $other = $this->createUnit('ulp');
        $admin = $this->createAdmin($root);

        $otherUser = User::factory()->create([
            'role' => 'security',
            'unit_id' => $other->id,
        ]);

        $superadmin = User::factory()->create([
            'role' => 'superadmin',
            'unit_id' => $root->id,
        ]);

        $adminPeer = User::factory()->create([
            'role' => 'admin',
            'unit_id' => $root->id,
        ]);

        $this->actingAs($admin)
            ->get("/admin/user/{$otherUser->id}/edit")
            ->assertForbidden();

        $this->actingAs($admin)
            ->put("/admin/user/{$otherUser->id}", $this->userPayload($otherUser))
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete("/admin/user/{$otherUser->id}")
            ->assertForbidden();

        $this->actingAs($admin)
            ->get("/admin/user/{$superadmin->id}/edit")
            ->assertForbidden();

        $this->actingAs($admin)
            ->put("/admin/user/{$superadmin->id}", $this->userPayload($superadmin))
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete("/admin/user/{$superadmin->id}")
            ->assertForbidden();

        $this->actingAs($admin)
            ->get("/admin/user/{$adminPeer->id}/edit")
            ->assertForbidden();

        $this->actingAs($admin)
            ->put("/admin/user/{$adminPeer->id}", $this->userPayload($adminPeer))
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete("/admin/user/{$adminPeer->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $otherUser->id]);
        $this->assertDatabaseHas('users', ['id' => $superadmin->id]);
        $this->assertDatabaseHas('users', ['id' => $adminPeer->id]);
    }

    public function test_admin_cannot_access_admin_account_management(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get('/admin/user?role=admin')
            ->assertForbidden();

        $this->actingAs($admin)
            ->get('/admin/user/create?role=admin')
            ->assertForbidden();

        $this->actingAs($admin)
            ->post('/admin/user?role=admin', [
                'username' => 'new_admin',
                'name' => 'New Admin',
                'email' => 'new-admin@example.test',
                'password' => 'password123',
                'unit_id' => null,
                'role' => 'admin',
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['username' => 'new_admin']);
    }

    public function test_admin_cannot_create_superadmin_or_user_outside_scope(): void
    {
        $root = $this->createUnit('up');
        $child = $this->createUnit('ulp', $root);
        $other = $this->createUnit('ulp');
        $admin = $this->createAdmin($root);

        $this->actingAs($admin)
            ->post('/admin/user?role=admin', [
                'username' => 'new_superadmin',
                'name' => 'New Superadmin',
                'email' => 'new-superadmin@example.test',
                'password' => 'password123',
                'unit_id' => $root->id,
                'role' => 'superadmin',
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['username' => 'new_superadmin']);

        $this->actingAs($admin)
            ->post('/admin/user?role=security', [
                'username' => 'security_as_superadmin',
                'name' => 'Security As Superadmin',
                'email' => 'security-as-superadmin@example.test',
                'password' => 'password123',
                'unit_id' => $root->id,
                'role' => 'superadmin',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['username' => 'security_as_superadmin']);

        $this->actingAs($admin)
            ->post('/admin/user?role=security', [
                'username' => 'no_unit',
                'name' => 'No Unit',
                'email' => 'no-unit@example.test',
                'password' => 'password123',
                'role' => 'security',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('unit_id');

        $this->actingAs($admin)
            ->post('/admin/user?role=security', [
                'username' => 'other_unit',
                'name' => 'Other Unit',
                'email' => 'other-unit@example.test',
                'password' => 'password123',
                'unit_id' => $other->id,
                'role' => 'security',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('unit_id');

        $this->actingAs($admin)
            ->post('/admin/user?role=security', [
                'username' => 'child_user',
                'name' => 'Child User',
                'email' => 'child-user@example.test',
                'password' => 'password123',
                'unit_id' => $child->id,
                'role' => 'security',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.user.index', ['role' => 'security']));

        $this->assertDatabaseHas('users', [
            'username' => 'child_user',
            'role' => 'security',
            'unit_id' => $child->id,
        ]);
    }

    public function test_admin_cannot_promote_security_to_admin_or_superadmin(): void
    {
        $root = $this->createUnit('up');
        $child = $this->createUnit('ulp', $root);
        $other = $this->createUnit('ulp');
        $admin = $this->createAdmin($root);

        $target = User::factory()->create([
            'username' => 'target_security',
            'role' => 'security',
            'unit_id' => $child->id,
        ]);

        $payload = $this->userPayload($target);

        $this->actingAs($admin)
            ->put("/admin/user/{$target->id}", array_merge($payload, ['role' => 'admin']))
            ->assertSessionHasErrors('role');

        $this->actingAs($admin)
            ->put("/admin/user/{$target->id}", array_merge($payload, ['role' => 'superadmin']))
            ->assertSessionHasErrors('role');

        $this->actingAs($admin)
            ->put("/admin/user/{$target->id}", array_merge($payload, ['unit_id' => $other->id]))
            ->assertSessionHasErrors('unit_id');

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'role' => 'security',
            'unit_id' => $child->id,
        ]);
    }

    public function test_admin_can_manage_security_users_in_scope(): void
    {
        $root = $this->createUnit('up');
        $child = $this->createUnit('ulp', $root);
        $admin = $this->createAdmin($root);

        $target = User::factory()->create([
            'username' => 'scoped_security',
            'name' => 'Scoped Security',
            'role' => 'security',
            'unit_id' => $child->id,
        ]);

        $this->actingAs($admin)
            ->get("/admin/user/{$target->id}/edit")
            ->assertOk();

        $this->actingAs($admin)
            ->put("/admin/user/{$target->id}", array_merge($this->userPayload($target), [
                'name' => 'Updated Security',
            ]))
            ->assertRedirect(route('admin.user.index', ['role' => 'security']));

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'name' => 'Updated Security',
            'role' => 'security',
        ]);

        $this->actingAs($admin)
            ->delete("/admin/user/{$target->id}")
            ->assertRedirect(route('admin.user.index', ['role' => 'security']));

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_superadmin_can_see_all_units_and_manage_any_user(): void
    {
        $this->createUnit('up');
        $other = $this->createUnit('ulp');
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $otherUser = User::factory()->create([
            'username' => 'managed_other',
            'role' => 'security',
            'unit_id' => $other->id,
        ]);

        $unitAdmin = User::factory()->create([
            'username' => 'managed_admin',
            'name' => 'Managed Admin',
            'role' => 'admin',
            'unit_id' => $other->id,
        ]);

        $this->actingAs($superadmin)
            ->get('/admin/unit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('units', 2));

        $this->actingAs($superadmin)
            ->get("/admin/user/{$otherUser->id}/edit")
            ->assertOk();

        $this->actingAs($superadmin)
            ->get("/admin/user/{$unitAdmin->id}/edit")
            ->assertOk();

        $this->actingAs($superadmin)
            ->put("/admin/user/{$unitAdmin->id}", array_merge($this->userPayload($unitAdmin), [
                'name' => 'Updated Admin',
            ]))
            ->assertRedirect(route('admin.user.index', ['role' => 'admin']));

        $this->assertDatabaseHas('users', [
            'id' => $unitAdmin->id,
            'name' => 'Updated Admin',
            'role' => 'admin',
        ]);

        $this->actingAs($superadmin)
            ->post('/admin/user?role=admin', [
                'username' => 'unit_admin_two',
                'name' => 'Unit Admin Two',
                'email' => 'unit-admin-two@example.test',
                'password' => 'password123',
                'unit_id' => $other->id,
                'role' => 'admin',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.user.index', ['role' => 'admin']));

        $this->assertDatabaseHas('users', [
            'username' => 'unit_admin_two',
            'role' => 'admin',
            'unit_id' => $other->id,
        ]);

        $createdAdmin = User::query()->where('username', 'unit_admin_two')->firstOrFail();

        $this->actingAs($superadmin)
            ->delete("/admin/user/{$createdAdmin->id}")
            ->assertRedirect(route('admin.user.index', ['role' => 'admin']));

        $this->assertDatabaseMissing('users', ['id' => $createdAdmin->id]);

        $this->actingAs($superadmin)
            ->post('/admin/user?role=admin', [
                'username' => 'superadmin_two',
                'name' => 'Superadmin Two',
                'email' => 'superadmin-two@example.test',
                'password' => 'password123',
                'unit_id' => null,
                'role' => 'superadmin',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.user.index', ['role' => 'admin']));

        $this->assertDatabaseHas('users', [
            'username' => 'superadmin_two',
            'role' => 'superadmin',
            'unit_id' => null,
        ]);
    }

    private function userPayload(User $user): array
    {
        return [
            'username' => $user->username,
            'name' => $user->name,
            'email' => $user->email,
            'unit_id' => $user->unit_id,
            'role' => $user->role,
            'is_active' => $user->is_active,
        ];
    }
}
