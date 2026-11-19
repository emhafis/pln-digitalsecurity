<?php

namespace Tests\Feature\Admin;

use App\Models\PatrolPoint;
use App\Models\SecurityPost;
use App\Models\User;
use App\Services\PatrolPointQrCodeService;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\Feature\Scheduling\SchedulingTestCase;

class PatrolPointControllerTest extends SchedulingTestCase
{
    public function test_index_lists_security_posts_not_patrol_points(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $this->createPatrolPoint($post, 'Titik A');
        $this->createPatrolPoint($post, 'Titik B');
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->get('/admin/patrol-point');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/PatrolPoint/Index')
            ->has('securityPosts', 1)
            ->where('securityPosts.0.name', $post->name)
            ->where('securityPosts.0.patrol_points_count', 2)
            ->missing('patrolPoints')
        );
    }

    public function test_index_returns_patrol_point_count_per_post(): void
    {
        $unit = $this->createUnit();
        $postA = $this->createPost($unit);
        $postA->update(['name' => 'Pos Alpha']);
        $postB = $this->createPost($unit);
        $postB->update(['name' => 'Pos Bravo']);
        $this->createPatrolPoint($postA, 'Titik A');
        $this->createPatrolPoint($postA, 'Titik B');
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->get('/admin/patrol-point');

        $response->assertInertia(fn (Assert $page) => $page
            ->has('securityPosts', 2)
            ->where('securityPosts.0.name', 'Pos Alpha')
            ->where('securityPosts.0.patrol_points_count', 2)
            ->where('securityPosts.1.name', 'Pos Bravo')
            ->where('securityPosts.1.patrol_points_count', 0)
        );
    }

    public function test_post_without_patrol_points_still_appears_on_index(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->get('/admin/patrol-point');

        $response->assertInertia(fn (Assert $page) => $page
            ->has('securityPosts', 1)
            ->where('securityPosts.0.id', $post->id)
            ->where('securityPosts.0.patrol_points_count', 0)
        );
    }

    public function test_index_pagination_and_count_are_based_on_posts(): void
    {
        $unit = $this->createUnit();
        SecurityPost::factory()->count(51)->create(['unit_id' => $unit->id]);
        $admin = $this->createAdmin($unit);

        $firstPage = $this->actingAs($admin)->get('/admin/patrol-point');
        $firstPage->assertInertia(fn (Assert $page) => $page
            ->has('securityPosts', 50)
            ->where('meta.total', 51)
            ->where('meta.current_page', 1)
            ->where('meta.last_page', 2)
        );

        $secondPage = $this->actingAs($admin)->get('/admin/patrol-point?page=2');
        $secondPage->assertInertia(fn (Assert $page) => $page
            ->has('securityPosts', 1)
            ->where('meta.total', 51)
            ->where('meta.current_page', 2)
        );
    }

    public function test_index_can_sort_by_unit_and_point_count(): void
    {
        $unitA = $this->createUnit();
        $unitA->update(['unit_name' => 'Unit Alpha']);
        $unitB = $this->createUnit();
        $unitB->update(['unit_name' => 'Unit Bravo']);
        $postA = $this->createPost($unitA);
        $postA->update(['name' => 'Pos Alpha']);
        $postB = $this->createPost($unitB);
        $postB->update(['name' => 'Pos Bravo']);
        $this->createPatrolPoint($postA, 'Titik A');
        $this->createPatrolPoint($postB, 'Titik B');
        $this->createPatrolPoint($postB, 'Titik C');
        $superadmin = User::factory()->create(['role' => 'superadmin', 'unit_id' => null]);

        $byUnit = $this->actingAs($superadmin)->get('/admin/patrol-point?sort=unit&dir=asc');
        $byUnit->assertInertia(fn (Assert $page) => $page
            ->where('securityPosts.0.id', $postA->id)
            ->where('securityPosts.1.id', $postB->id)
        );

        $byPoints = $this->actingAs($superadmin)->get('/admin/patrol-point?sort=points&dir=desc');
        $byPoints->assertInertia(fn (Assert $page) => $page
            ->where('securityPosts.0.id', $postB->id)
            ->where('securityPosts.1.id', $postA->id)
        );
    }

    public function test_index_filters_by_unit(): void
    {
        $unitA = $this->createUnit('ulp');
        $unitChild = $this->createUnit('ulp', $unitA);
        $unitB = $this->createUnit('ulp');
        $postA = $this->createPost($unitA);
        $postChild = $this->createPost($unitChild);
        $this->createPost($unitB);
        $admin = $this->createAdmin($unitA);

        $response = $this->actingAs($admin)->get('/admin/patrol-point?unit_id='.$unitA->id);

        $response->assertInertia(fn (Assert $page) => $page
            ->has('securityPosts', 1)
            ->where('securityPosts.0.id', $postA->id)
            ->where('unit_id', $unitA->id)
        );

        $childResponse = $this->actingAs($admin)->get('/admin/patrol-point?unit_id='.$unitChild->id);

        $childResponse->assertInertia(fn (Assert $page) => $page
            ->has('securityPosts', 1)
            ->where('securityPosts.0.id', $postChild->id)
            ->where('unit_id', $unitChild->id)
        );
    }

    public function test_index_search_matches_post_unit_and_patrol_point_name(): void
    {
        $unit = $this->createUnit();
        $unit->update(['unit_name' => 'Unit Pencarian']);
        $post = $this->createPost($unit);
        $post->update(['name' => 'Pos Alpha']);
        $this->createPatrolPoint($post, 'Gerbang Utama');
        $admin = $this->createAdmin($unit);

        $byPost = $this->actingAs($admin)->get('/admin/patrol-point?search=Alpha');
        $byPost->assertInertia(fn (Assert $page) => $page
            ->has('securityPosts', 1)
            ->where('securityPosts.0.id', $post->id)
        );

        $byCode = $this->actingAs($admin)->get('/admin/patrol-point?search='.$post->post_code);
        $byCode->assertInertia(fn (Assert $page) => $page
            ->has('securityPosts', 1)
            ->where('securityPosts.0.id', $post->id)
        );

        $byUnit = $this->actingAs($admin)->get('/admin/patrol-point?search=Pencarian');
        $byUnit->assertInertia(fn (Assert $page) => $page
            ->has('securityPosts', 1)
            ->where('securityPosts.0.id', $post->id)
        );

        $byPoint = $this->actingAs($admin)->get('/admin/patrol-point?search=Gerbang');
        $byPoint->assertInertia(fn (Assert $page) => $page
            ->has('securityPosts', 1)
            ->where('securityPosts.0.id', $post->id)
        );
    }

    public function test_admin_cannot_see_posts_outside_unit_scope(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        $postA = $this->createPost($unitA);
        $this->createPost($unitB);
        $admin = $this->createAdmin($unitA);

        $response = $this->actingAs($admin)->get('/admin/patrol-point');

        $response->assertInertia(fn (Assert $page) => $page
            ->has('securityPosts', 1)
            ->where('securityPosts.0.id', $postA->id)
        );
    }

    public function test_superadmin_can_see_posts_from_all_units(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        $this->createPost($unitA);
        $this->createPost($unitB);
        $superadmin = User::factory()->create(['role' => 'superadmin', 'unit_id' => null]);

        $response = $this->actingAs($superadmin)->get('/admin/patrol-point');

        $response->assertInertia(fn (Assert $page) => $page
            ->has('securityPosts', 2)
            ->where('unit_id', '')
        );
    }

    public function test_show_post_only_returns_its_own_patrol_points(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $otherPost = $this->createPost($unit);
        $this->createPatrolPoint($post, 'Zulu');
        $this->createPatrolPoint($post, 'Alpha');
        $this->createPatrolPoint($otherPost, 'Lain');
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->get('/admin/patrol-point/post/'.$post->id);

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/PatrolPoint/ShowPost')
            ->where('securityPost.id', $post->id)
            ->where('securityPost.patrol_points_count', 2)
            ->has('patrolPoints', 2)
            ->where('patrolPoints.0.name', 'Alpha')
            ->where('patrolPoints.1.name', 'Zulu')
        );
    }

    public function test_admin_cannot_show_post_outside_unit_scope(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        $postB = $this->createPost($unitB);
        $admin = $this->createAdmin($unitA);

        $this->actingAs($admin)
            ->get('/admin/patrol-point/post/'.$postB->id)
            ->assertForbidden();
    }

    public function test_qr_pdf_returns_application_pdf(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $this->createPatrolPoint($post, 'Titik A');
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->get('/admin/patrol-point/post/'.$post->id.'/qr.pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('/Subtype /Image', $response->getContent());
    }

    public function test_qr_pdf_content_disposition_is_inline(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $this->createPatrolPoint($post, 'Titik A');
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->get('/admin/patrol-point/post/'.$post->id.'/qr.pdf');

        $response->assertOk();
        $this->assertStringContainsString(
            'inline',
            (string) $response->headers->get('content-disposition')
        );
        $this->assertStringContainsString(
            Str::slug($post->name),
            (string) $response->headers->get('content-disposition')
        );
    }

    public function test_qr_pdf_uses_existing_patrol_point_uuid_as_payload(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $uuid = (string) Str::uuid();
        $this->createPatrolPoint($post, 'Titik A', $uuid);
        $admin = $this->createAdmin($unit);

        $this->mock(PatrolPointQrCodeService::class, function (MockInterface $mock) use ($uuid) {
            $mock->shouldReceive('dataUri')
                ->once()
                ->with($uuid)
                ->andReturn($this->fakePngDataUri());
        });

        $response = $this->actingAs($admin)->get('/admin/patrol-point/post/'.$post->id.'/qr.pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_qr_pdf_for_post_without_points_redirects_with_warning(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->get('/admin/patrol-point/post/'.$post->id.'/qr.pdf');

        $response->assertRedirect(route('admin.patrol-point.post.show', $post->id));
        $response->assertSessionHas('warning');
    }

    public function test_qr_pdf_rejects_post_outside_unit_scope(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        $postB = $this->createPost($unitB);
        $this->createPatrolPoint($postB, 'Titik B');
        $admin = $this->createAdmin($unitA);

        $this->actingAs($admin)
            ->get('/admin/patrol-point/post/'.$postB->id.'/qr.pdf')
            ->assertForbidden();
    }

    public function test_create_patrol_point_generates_uuid_and_redirects_to_post(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->post('/admin/patrol-point', [
            'name' => 'Titik Baru',
            'security_post_id' => $post->id,
        ]);

        $patrolPoint = PatrolPoint::query()->firstOrFail();

        $response->assertRedirect(route('admin.patrol-point.post.show', $post->id));
        $response->assertSessionHasNoErrors();
        $this->assertTrue(Str::isUuid($patrolPoint->qr_code));
        $this->assertDatabaseHas('patrol_points', [
            'id' => $patrolPoint->id,
            'security_post_id' => $post->id,
            'qr_code' => $patrolPoint->qr_code,
        ]);
    }

    public function test_update_does_not_change_patrol_point_uuid(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $uuid = (string) Str::uuid();
        $patrolPoint = $this->createPatrolPoint($post, 'Titik Lama', $uuid);
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->put('/admin/patrol-point/'.$patrolPoint->id, [
            'name' => 'Titik Baru',
            'security_post_id' => $post->id,
        ]);

        $response->assertRedirect(route('admin.patrol-point.post.show', $post->id));
        $response->assertSessionHasNoErrors();
        $this->assertSame($uuid, $patrolPoint->fresh()->qr_code);
    }

    public function test_delete_patrol_point_redirects_to_its_post(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $patrolPoint = $this->createPatrolPoint($post, 'Titik A');
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->delete('/admin/patrol-point/'.$patrolPoint->id);

        $response->assertRedirect(route('admin.patrol-point.post.show', $post->id));
        $this->assertDatabaseMissing('patrol_points', ['id' => $patrolPoint->id]);
    }

    private function createPatrolPoint(SecurityPost $securityPost, string $name, ?string $qrCode = null): PatrolPoint
    {
        return PatrolPoint::create([
            'name' => $name,
            'security_post_id' => $securityPost->id,
            'qr_code' => $qrCode ?? (string) Str::uuid(),
        ]);
    }

    private function fakePngDataUri(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
    }
}
