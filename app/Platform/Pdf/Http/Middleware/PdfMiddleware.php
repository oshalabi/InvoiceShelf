<?php

namespace App\Platform\Pdf\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the web-served PDF routes.
 *
 * A PDF is reachable by staff in the admin session, by an API token, and by a
 * customer signed into the portal, so any one of the three guards is enough.
 * Nobody recognised is sent to the login page rather than refused outright,
 * since these URLs are typically opened straight from a browser.
 */
class PdfMiddleware
{
    /**
     * Let the request through as soon as one of the guards recognises it.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        foreach (['web', 'sanctum', 'customer'] as $guard) {
            if (Auth::guard($guard)->check()) {
                return $next($request);
            }
        }

        return redirect('/login');
    }
}
