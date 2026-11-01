<?php

namespace App\Http\Middleware;

use App\Services\Setup\SetupStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Direct a fresh install to setup until its first administrator exists. */
class EnsureInitialSetup
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->runningUnitTests()) {
            return $next($request);
        }

        if (! app(SetupStatus::class)->isRequired()) {
            if ($request->routeIs('setup.index')) {
                return redirect()->route(auth()->check() ? 'dashboard' : 'login');
            }

            return $next($request);
        }

        if ($request->routeIs('setup.index')) {
            return $next($request);
        }

        return redirect()->route('setup.index');
    }
}
