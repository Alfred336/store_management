<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Log out any authenticated user whose account has been deactivated.
 * This applies on every web request so deactivation takes effect immediately
 * even for sessions that were open when the account was deactivated.
 */
class EnforceActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && ! Auth::user()->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Your account has been deactivated. Please contact your administrator.']);
        }

        return $next($request);
    }
}
