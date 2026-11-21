<?php

namespace Tests\Feature\Storage;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthenticatedStorageAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_storage_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('patrol-incidents/test.jpg', 'fake-image');

        $response = $this->get('/storage/patrol-incidents/test.jpg');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_admin_can_access_storage_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('patrol-incidents/test.jpg', 'fake-image');
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($user)->get('/storage/patrol-incidents/test.jpg');

        $response->assertOk();
        $this->assertStringContainsString('image', (string) $response->headers->get('content-type'));
    }

    public function test_authenticated_security_can_access_storage_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('patrol-incidents/test.jpg', 'fake-image');
        $user = User::factory()->create(['role' => 'security', 'is_active' => true]);

        $response = $this->actingAs($user)->get('/storage/patrol-incidents/test.jpg');

        $response->assertOk();
    }

    public function test_storage_path_traversal_is_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($user)->get('/storage/../.env');

        $response->assertNotFound();
    }
}
