<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirect authenticated users who must change their password to the
 * dedicated change-password page before they can access anything else.
 *
 * Registered both in the web middleware stack (for initial page loads) and as
 * Livewire persistent middleware (for wire update calls).  When running as
 * persistent middleware, Livewire passes a fake request built from the signed
 * snapshot memo path, so the route check correctly exempts components that were
 * originally rendered on the password.change page — without relying on the
 * client-controlled Referer header.
 */
class EnforceMustChangePassword
{
    /** Routes exempt from the forced redirect. */
    private const EXEMPT_ROUTES = [
        'password.change',
        'logout',
        'profile.edit',
        'appearance.edit',
        'security.edit',
        'well-known.passkeys',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && Auth::user()->must_change_password) {
            foreach (self::EXEMPT_ROUTES as $pattern) {
                if ($request->routeIs($pattern)) {
                    return $next($request);
                }
            }

            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
