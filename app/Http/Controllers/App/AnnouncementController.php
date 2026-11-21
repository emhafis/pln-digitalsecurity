<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function index(): Response
    {
        $announcements = Announcement::query()
            ->published()
            ->orderByRaw('COALESCE(published_at, created_at) DESC')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Announcement $a) => $this->item($a))
            ->values();

        return Inertia::render('App/Announcements/Index', [
            'announcements' => $announcements,
        ]);
    }

    public function show(int $id): Response
    {
        $announcement = Announcement::query()->published()->findOrFail($id);

        return Inertia::render('App/Announcements/Show', [
            'announcement' => $this->item($announcement),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function item(Announcement $announcement): array
    {
        $date = $announcement->published_at ?? $announcement->created_at;

        return [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'content' => $announcement->content,
            'excerpt' => $announcement->excerpt,
            'image_url' => $announcement->image_url,
            'published_at' => $announcement->published_at?->toIso8601String(),
            'date_label' => $date?->locale('id')->translatedFormat('d M Y'),
        ];
    }
}
