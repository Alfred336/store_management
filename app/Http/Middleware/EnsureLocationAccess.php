<?php

namespace App\Http\Middleware;

use App\Services\Access\LocationAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure non-Admin users have at least one active location assigned.
 *
 * Users with no location can still log in, change their password, and reach
 * their profile settings, but all data screens redirect here.
 *
 * Also auto-selects the current location when the user has exactly one.
 */
class EnsureLocationAccess
{
    public function __construct(private readonly LocationAccess $locationAccess) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        // Admin is always allowed everywhere.
        if ($user->hasRole('Admin')) {
            return $next($request);
        }

        // Exempt: auth, setup, password change, profile/security, and the
        // no-location page itself — so we never create a redirect loop.
        if ($request->routeIs(
            'login', 'logout', 'password.*',
            'setup.*',
            'location.none',
            'profile.*', 'security.*', 'appearance.*',
            'two-factor.*', 'passkey.*',
            'verification.*',
        )) {
            return $next($request);
        }

        // Exempt Livewire internal endpoints (route name contains 'livewire').
        if ($request->routeIs('*livewire*')) {
            return $next($request);
        }

        $allowed = $this->locationAccess->allowedLocationIds($user);

        if (empty($allowed)) {
            return redirect()->route('location.none');
        }

        // Auto-persist the current location when the user has exactly one.
        $this->locationAccess->currentLocationId($user);

        return $next($request);
    }
}
