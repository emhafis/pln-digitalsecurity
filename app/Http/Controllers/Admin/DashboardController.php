<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityPost;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Admin dashboard (admin layout).
     */
    public function index(Request $request): Response
    {
        $scope = $this->unitScope($request);

        $securityPosts = SecurityPost::query()
            ->with('unit:id,unit_name')
            ->when($scope !== null, fn ($q) => $q->whereIn('unit_id', $scope))
            ->orderBy('name')
            ->get(['id', 'name', 'address', 'latitude', 'longitude', 'unit_id'])
            ->map(fn (SecurityPost $post) => [
                'id' => $post->id,
                'name' => $post->name,
                'address' => $post->address,
                'latitude' => $post->latitude,
                'longitude' => $post->longitude,
                'unit_name' => $post->unit?->unit_name,
            ])
            ->values();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'units' => 12,
                'posts' => 28,
                'guards' => 145,
                'users' => 36,
            ],
            'securityPosts' => $securityPosts,
        ]);
    }

    /**
     * Unit scope for the current user.
     *
     * - null  => unlimited (superadmin).
     * - array => allowed unit ids (user unit + descendants), [] when none.
     */
    private function unitScope(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        if ($user->role === 'superadmin') {
            return null;
        }

        if (! in_array($user->role, ['security', 'admin'], true) || $user->unit_id === null) {
            return [];
        }

        return array_map('intval', $user->unit?->descendantIds() ?? [$user->unit_id]);
    }
}
