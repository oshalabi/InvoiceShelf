<?php

namespace App\Domains\Accounts\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as FrameworkAuthenticate;
use Illuminate\Http\Request;

/**
 * The `auth` alias for staff routes.
 *
 * Everything about deciding who is signed in is inherited; the one thing
 * settled here is where a browser that is not signed in gets sent.
 */
class Authenticate extends FrameworkAuthenticate
{
    /**
     * Name the page an unauthenticated caller should be bounced to.
     *
     * API clients get nothing back, which is what makes the framework raise a
     * 401 for them instead of a redirect. The parent already suppresses the
     * redirect for JSON callers, so the branch below is belt and braces.
     *
     * @param  Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        return $request->expectsJson() ? null : route('login');
    }
}
