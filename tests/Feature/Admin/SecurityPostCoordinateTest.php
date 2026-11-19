<?php

namespace Tests\Feature\Admin;

use App\Models\SecurityPost;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Scheduling\SchedulingTestCase;

class SecurityPostCoordinateTest extends SchedulingTestCase
{
    public function test_create_page_uses_security_post_form_with_map(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->get('/admin/security-post/create');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/SecurityPost/Create')
            ->has('units', 1)
        );
    }

    public function test_edit_page_exposes_existing_coordinates(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $post->update([
            'latitude' => 5.5622464,
            'longitude' => 95.3348073,
        ]);
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->get('/admin/security-post/'.$post->id.'/edit');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/SecurityPost/Edit')
            ->where('securityPost.latitude', 5.5622464)
            ->where('securityPost.longitude', 95.3348073)
        );
    }

    public function test_update_persists_new_coordinates(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->put('/admin/security-post/'.$post->id, [
            'name' => $post->name,
            'address' => $post->address,
            'latitude' => 5.5482900,
            'longitude' => 95.3237500,
            'unit_id' => $unit->id,
        ]);

        $response->assertRedirect(route('admin.security-post.show', $post->id));
        $response->assertSessionHasNoErrors();

        $post->refresh();

        $this->assertSame(5.54829, (float) $post->latitude);
        $this->assertSame(95.32375, (float) $post->longitude);
    }

    public function test_store_allows_empty_coordinates(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->post('/admin/security-post', [
            'name' => 'Pos Tanpa Koordinat',
            'address' => 'Banda Aceh',
            'latitude' => '',
            'longitude' => '',
            'unit_id' => $unit->id,
        ]);

        $response->assertSessionHasNoErrors();

        $post = SecurityPost::query()->where('name', 'Pos Tanpa Koordinat')->firstOrFail();

        $this->assertNull($post->latitude);
        $this->assertNull($post->longitude);
    }

    public function test_index_reports_post_without_latitude(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $post->update([
            'name' => 'Pos Tanpa Latitude',
            'latitude' => null,
            'longitude' => 95.3348073,
        ]);
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->get('/admin/security-post');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('postsWithoutCoordinates', 1)
            ->has('postsNeedingCoordinates', 1)
            ->where('postsNeedingCoordinates.0.id', $post->id)
            ->where('postsNeedingCoordinates.0.name', 'Pos Tanpa Latitude')
            ->where('postsNeedingCoordinates.0.unit_name', $unit->unit_name)
        );
    }

    public function test_index_reports_post_without_longitude(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $post->update([
            'name' => 'Pos Tanpa Longitude',
            'latitude' => 5.5622464,
            'longitude' => null,
        ]);
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->get('/admin/security-post');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('postsWithoutCoordinates', 1)
            ->has('postsNeedingCoordinates', 1)
            ->where('postsNeedingCoordinates.0.id', $post->id)
        );
    }

    public function test_post_with_both_coordinates_is_not_counted(): void
    {
        $unit = $this->createUnit();
        $completePost = $this->createPost($unit);
        $completePost->update([
            'name' => 'Pos Lengkap',
            'latitude' => 5.5622464,
            'longitude' => 95.3348073,
        ]);
        $incompletePost = $this->createPost($unit);
        $incompletePost->update([
            'name' => 'Pos Belum Lengkap',
            'latitude' => 5.5622464,
            'longitude' => null,
        ]);
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->get('/admin/security-post');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('postsWithoutCoordinates', 1)
            ->has('postsNeedingCoordinates', 1)
            ->where('postsNeedingCoordinates.0.id', $incompletePost->id)
        );
    }

    public function test_coordinate_warning_count_and_list_follow_unit_scope(): void
    {
        $unitA = $this->createUnit('ulp');
        $unitChild = $this->createUnit('ulp', $unitA);
        $unitB = $this->createUnit('ulp');

        $postA = $this->createPost($unitA);
        $postA->update(['name' => 'Pos Alpha', 'latitude' => null, 'longitude' => null]);
        $postChild = $this->createPost($unitChild);
        $postChild->update(['name' => 'Pos Child', 'latitude' => null, 'longitude' => null]);
        $postB = $this->createPost($unitB);
        $postB->update(['name' => 'Pos Bravo', 'latitude' => null, 'longitude' => null]);

        $admin = $this->createAdmin($unitA);
        $adminResponse = $this->actingAs($admin)->get('/admin/security-post');

        $adminResponse->assertInertia(fn (Assert $page) => $page
            ->where('postsWithoutCoordinates', 2)
            ->has('postsNeedingCoordinates', 2)
            ->where('postsNeedingCoordinates.0.id', $postA->id)
            ->where('postsNeedingCoordinates.1.id', $postChild->id)
        );

        $superadmin = User::factory()->create(['role' => 'superadmin', 'unit_id' => null]);
        $superadminResponse = $this->actingAs($superadmin)->get('/admin/security-post');

        $superadminResponse->assertInertia(fn (Assert $page) => $page
            ->where('postsWithoutCoordinates', 3)
            ->has('postsNeedingCoordinates', 3)
        );
    }

    public function test_coordinate_warning_is_empty_when_all_posts_have_coordinates(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $post->update([
            'latitude' => 5.5622464,
            'longitude' => 95.3348073,
        ]);
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->get('/admin/security-post');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('postsWithoutCoordinates', 0)
            ->has('postsNeedingCoordinates', 0)
        );
    }

    public function test_coordinate_warning_includes_posts_outside_current_page(): void
    {
        $unit = $this->createUnit();
        SecurityPost::factory()->count(50)->create([
            'unit_id' => $unit->id,
            'latitude' => 5.5622464,
            'longitude' => 95.3348073,
        ]);
        $missingPost = SecurityPost::factory()->create([
            'unit_id' => $unit->id,
            'name' => 'Zulu Missing',
            'latitude' => null,
            'longitude' => null,
        ]);
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->get('/admin/security-post');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('meta.current_page', 1)
            ->has('securityPosts', 50)
            ->where('postsWithoutCoordinates', 1)
            ->has('postsNeedingCoordinates', 1)
            ->where('postsNeedingCoordinates.0.id', $missingPost->id)
        );
    }
}
