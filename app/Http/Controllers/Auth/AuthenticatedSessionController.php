<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login screen (guest layout, no registration).
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canRegister' => false,
        ]);
    }

    /**
     * Authenticate the incoming user.
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'username' => __('auth.failed'),
            ])->onlyInput('username');
        }

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'username' => 'Akun Anda tidak aktif.',
            ])->onlyInput('username');
        }

        $request->session()->regenerate();

        app(ActivityLogService::class)->log([
            'action' => 'login',
            'module' => 'Auth',
            'description' => 'User berhasil login: '.($user->name ?? $user->username),
            'subject' => $user,
            'unit_id' => $user->unit_id,
            'new_values' => ['role' => $user->role, 'username' => $user->username],
        ]);

        return redirect()->intended(
            in_array($user->role, ['superadmin', 'admin'], true)
                ? route('admin.dashboard')
                : route('app.dashboard'),
        );
    }

    /**
     * Log the authenticated user out.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user) {
            app(ActivityLogService::class)->log([
                'action' => 'logout',
                'module' => 'Auth',
                'description' => 'User logout: '.($user->name ?? $user->username),
                'subject' => $user,
                'unit_id' => $user->unit_id,
                'old_values' => ['role' => $user->role, 'username' => $user->username],
            ]);
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
