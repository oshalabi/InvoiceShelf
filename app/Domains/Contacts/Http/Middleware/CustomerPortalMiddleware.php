<?php

namespace App\Domains\Contacts\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Re-reads the portal switch on every authenticated portal request.
 *
 * It sits behind `auth:customer`, so a session already exists by the time it
 * runs; what it adds is revocation. Turning a contact's portal access off
 * ends their session on their very next call, not at their next sign-in.
 */
class CustomerPortalMiddleware
{
    /**
     * Let the request through only while portal access is still granted.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $portal = auth()->guard('customer');

        if ($portal->user()->enable_portal) {
            return $next($request);
        }

        // Access was withdrawn mid-session: tear the session down too, so the
        // SPA lands back on the login form instead of retrying.
        $portal->logout();

        return response('Unauthorized.', Response::HTTP_UNAUTHORIZED);
    }
}
