<?php

namespace Tests\Feature\Admin;

use App\Models\Announcement;
use App\Models\User;
use App\Support\AnnouncementImageStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        return User::factory()->create(['role' => 'superadmin', 'is_active' => true]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function securityUser(): User
    {
        return User::factory()->create(['role' => 'security', 'is_active' => true]);
    }

    public function test_superadmin_can_create_announcement_with_image(): void
    {
        Storage::fake('public');
        $user = $this->superadmin();

        $response = $this->actingAs($user)->post(route('admin.announcements.store'), [
            'title' => 'Pengumuman Penting',
            'content' => 'Isi pengumuman untuk semua security.',
            'image' => UploadedFile::fake()->image('info.jpg', 1200, 600),
            'is_active' => '1',
            'published_at' => now()->subHour()->format('Y-m-d\TH:i'),
        ]);

        $response->assertRedirect(route('admin.announcements.index'));
        $announcement = Announcement::query()->first();
        $this->assertNotNull($announcement);
        $this->assertSame('Pengumuman Penting', $announcement->title);
        $this->assertTrue($announcement->is_active);
        $this->assertSame($user->id, $announcement->created_by);
        $this->assertNotNull($announcement->image_path);
        Storage::disk('public')->assertExists($announcement->image_path);
    }

    public function test_superadmin_can_update_and_delete_announcement_image(): void
    {
        Storage::fake('public');
        $user = $this->superadmin();
        $oldPath = AnnouncementImageStorage::store(UploadedFile::fake()->image('old.jpg'));
        $announcement = Announcement::create([
            'title' => 'Lama',
            'content' => 'Konten lama',
            'image_path' => $oldPath,
            'is_active' => true,
            'published_at' => now(),
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->put(route('admin.announcements.update', $announcement), [
            'title' => 'Baru',
            'content' => 'Konten baru',
            'image' => UploadedFile::fake()->image('new.jpg'),
            'is_active' => '1',
            'published_at' => now()->format('Y-m-d\TH:i'),
        ]);

        $response->assertRedirect(route('admin.announcements.index'));
        $announcement->refresh();
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($announcement->image_path);
        $this->assertSame('Baru', $announcement->title);

        $newPath = $announcement->image_path;
        $this->actingAs($user)->delete(route('admin.announcements.destroy', $announcement))
            ->assertRedirect(route('admin.announcements.index'));
        $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
        Storage::disk('public')->assertMissing($newPath);
    }

    public function test_admin_and_security_cannot_access_admin_announcement_crud(): void
    {
        $announcement = Announcement::create([
            'title' => 'Test',
            'content' => 'Konten',
            'is_active' => true,
            'published_at' => now(),
        ]);

        $this->actingAs($this->admin())->get(route('admin.announcements.index'))->assertForbidden();
        $this->actingAs($this->admin())->post(route('admin.announcements.store'), [])->assertForbidden();
        $this->actingAs($this->securityUser())->get(route('admin.announcements.index'))->assertForbidden();
        $this->actingAs($this->securityUser())->delete(route('admin.announcements.destroy', $announcement))->assertForbidden();
    }

    public function test_app_shows_only_published_active_announcements(): void
    {
        $security = $this->securityUser();
        \App\Models\Security::factory()->create(['user_id' => $security->id, 'is_active' => true]);

        Announcement::create(['title' => 'Aktif', 'content' => 'Tampil', 'is_active' => true, 'published_at' => now()->subMinute()]);
        Announcement::create(['title' => 'Nonaktif', 'content' => 'Sembunyi', 'is_active' => false, 'published_at' => now()->subMinute()]);
        Announcement::create(['title' => 'Masa Depan', 'content' => 'Sembunyi', 'is_active' => true, 'published_at' => now()->addDay()]);

        $home = $this->actingAs($security)->get('/app');
        $home->assertOk();
        $home->assertInertia(fn ($page) => $page
            ->component('App/Home')
            ->has('announcements', 1)
            ->where('announcements.0.title', 'Aktif')
        );

        $list = $this->actingAs($security)->get('/app/announcements');
        $list->assertOk();
        $list->assertInertia(fn ($page) => $page
            ->component('App/Announcements/Index')
            ->has('announcements', 1)
            ->where('announcements.0.title', 'Aktif')
        );
    }
}
