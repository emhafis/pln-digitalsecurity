<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Security;
use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        if ($user === null || $user->role !== 'security') {
            abort(403);
        }

        $security = Security::query()->where('user_id', $user->id)->first();
        if ($security === null) {
            abort(403);
        }

        $membership = $security->activeSecurityTeamMembership()->with('securityTeam.securityPost')->first();

        return Inertia::render('App/Profile/Index', [
            'user' => $user,
            'security' => array_merge($security->toArray(), [
                'photo_url' => PublicStorageUrl::for($security->photo),
            ]),
            'unit' => $user->unit,
            'membership' => $membership,
        ]);
    }

    public function password(Request $request)
    {
        $user = $request->user();
        if ($user === null || $user->role !== 'security') {
            abort(403);
        }

        $security = Security::query()->where('user_id', $user->id)->first();
        if ($security === null) {
            abort(403);
        }

        return Inertia::render('App/Profile/ChangePassword');
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();
        if ($user === null || $user->role !== 'security') {
            abort(403);
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Password saat ini tidak sesuai.']);
        }

        $user->update(['password' => Hash::make($validated['password'])]);

        return back()->with('status', 'Password berhasil diperbarui.');
    }
}
