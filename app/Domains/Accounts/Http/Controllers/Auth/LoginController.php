<?php

namespace App\Domains\Accounts\Http\Controllers\Auth;

use App\Platform\Http\Controller;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers as OpensSessions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Cookie-session sign-in for the admin shell.
 *
 * This is the second, older way into the admin app, distinct from the bearer
 * flow next door: it opens a `web` session instead of minting a token. The
 * imported trait supplies the whole of it (field validation, the lockout
 * counter, the guard attempt and the success reply), so the only things
 * declared here are where a browser lands afterwards and how signing out is
 * handled.
 *
 * Quirk kept as is: the two sign-in doors disagree about their input. This one
 * validates `email` (the trait's default field name), while the token endpoint
 * takes `username`, so the same payload will not satisfy both.
 */
class LoginController extends Controller
{
    use OpensSessions;

    /**
     * Where a browser is sent once a session has been opened.
     *
     * Read by the trait through `redirectPath()`; only reached when the
     * caller did not ask for JSON, which the SPA always does.
     *
     * @var string
     */
    protected $redirectTo = AppServiceProvider::HOME;

    /**
     * Fence the sign-in action off from anyone already holding a session.
     *
     * Signing out is the one action exempted, for the obvious reason that it
     * is only ever useful while signed in. Note the guest guard redirects an
     * already-authenticated caller to the dashboard rather than refusing.
     */
    public function __construct()
    {
        $this->middleware('guest', ['except' => 'logout']);
    }

    /**
     * Close the session opened by this controller.
     *
     * The trait's own version is replaced because it answers with a redirect
     * or a 204; this one returns nothing at all, which the framework renders
     * as an empty 200. Flushing the session and then rotating the CSRF token
     * is what stops the emptied session from being reused.
     */
    public function logout(Request $request): void
    {
        Auth::guard('web')->logout();

        $session = $request->session();
        $session->invalidate();
        $session->regenerateToken();
    }
}
