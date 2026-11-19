<?php

namespace App\Http\Middleware;

use App\Models\Security;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSecurityProfile
{
    /**
     * Require the authenticated security user to have a linked Security profile.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $hasProfile = $user !== null
            && $user->role === 'security'
            && Security::query()->where('user_id', $user->id)->exists();

        abort_unless($hasProfile, 403, 'Akun Anda belum terhubung dengan data Satpam.');

        return $next($request);
    }
}
