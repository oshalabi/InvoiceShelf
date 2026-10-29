<?php

namespace App\Domains\Accounts\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The mirror of the guest alias: keeps signed-out visitors off the admin shell.
 *
 * It guards the page that boots the SPA, so a visitor without a session is
 * shown the sign-in page instead of an app that would immediately fail every
 * request it makes.
 *
 * Quirk kept as is: the name says "unauthorized", but nothing about
 * permissions is examined. The only question asked is whether anyone is
 * signed in at all.
 */
class RedirectIfUnauthorized
{
    /**
     * Hard-coded rather than resolved from the named route, so it stays put
     * even if the route's name changes.
     */
    private const SIGN_IN_PATH = '/login';

    /**
     * Let a signed-in caller through, and send everyone else to sign in.
     *
     * As with the guest alias, an unnamed guard means the default one, so
     * this asks about staff sessions only.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $guard = null): Response
    {
        if (! Auth::guard($guard)->check()) {
            return redirect(self::SIGN_IN_PATH);
        }

        return $next($request);
    }
}
