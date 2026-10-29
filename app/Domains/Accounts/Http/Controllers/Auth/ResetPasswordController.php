<?php

namespace App\Domains\Accounts\Http\Controllers\Auth;

use App\Platform\Http\Controller;
use App\Providers\AppServiceProvider;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Auth\ResetsPasswords as ConsumesResetTokens;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * Spends a reset token and stores the password chosen with it.
 *
 * Token lookup, expiry checking and the success/failure branch all come from
 * the imported trait, as do the input rules: a token, an address, and a
 * password that must be confirmed and satisfy the framework's default
 * strength rules. The broker is left at its default, so tokens are checked
 * against the staff table.
 *
 * Three things are narrowed below: the replies become JSON and plain text,
 * the write hands the password over unhashed, and the account is deliberately
 * left signed out afterwards.
 */
class ResetPasswordController extends Controller
{
    use ConsumesResetTokens;

    /**
     * Covers every way a token can fail: absent, expired, forged, or minted
     * for a different address than the one submitted with it.
     */
    private const REFUSED = 'Failed, Invalid Token.';

    /**
     * Where the trait would send a browser after a successful reset.
     *
     * Inert in practice, since both replies below are written by hand, but
     * the trait reads the property, so it stays declared.
     *
     * @var string
     */
    protected $redirectTo = AppServiceProvider::HOME;

    /**
     * Store the password chosen behind the token.
     *
     * Two deliberate departures from the trait. The value is handed over raw,
     * because the model hashes it on assignment and running it through the
     * hasher here would store a hash of a hash. And no session is opened
     * afterwards. The trait would sign the account straight in; here it comes
     * back through the login form instead.
     *
     * @param  mixed  $user  the account the token was minted for
     * @param  string  $password
     * @return void
     */
    protected function resetPassword($user, $password)
    {
        // Assigning the attribute is what runs the model's hashing mutator.
        $user->setAttribute('password', $password);

        $rotated = Str::random(60);
        $user->setRememberToken($rotated);

        $user->save();

        Event::dispatch(new PasswordReset($user));
    }

    /**
     * Confirm the token was spent and the password replaced.
     *
     * @param  string  $response
     */
    protected function sendResetResponse(Request $request, $response)
    {
        return response()->json(['message' => 'Password reset successfully.']);
    }

    /**
     * Refuse a token that did not check out.
     *
     * Quirk kept as is: unlike the JSON everything else on this prefix
     * answers with, the refusal is a bare plain-text body carrying a 403, so
     * a client parsing the reply has to special-case this one path.
     *
     * @param  string  $response
     */
    protected function sendResetFailedResponse(Request $request, $response)
    {
        return response(self::REFUSED, Response::HTTP_FORBIDDEN);
    }
}
