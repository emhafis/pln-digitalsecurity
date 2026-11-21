<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\App\Concerns\BuildsAttendanceContext;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use BuildsAttendanceContext;

    public function index(Request $request): Response
    {
        $announcements = Announcement::query()
            ->published()
            ->orderByRaw('COALESCE(published_at, created_at) DESC')
            ->orderByDesc('id')
            ->limit(4)
            ->get()
            ->map(fn (Announcement $announcement) => AnnouncementController::item($announcement))
            ->values();

        return Inertia::render('App/Home', [
            ...$this->attendanceContext($request),
            'announcements' => $announcements,
        ]);
    }
}
