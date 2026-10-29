<?php

namespace App\Domains\Contacts\Http\Controllers\CustomerPortal\Auth;

use App\Platform\Http\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Foundation\Auth\ResetsPasswords as ConsumesResetTokens;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Spends a portal reset token and stores the password chosen with it.
 *
 * Token verification, input validation and the success/failure branch all
 * come from the imported trait. The overrides below narrow it to the customer
 * broker, to JSON replies, and to a write that leaves the contact signed out.
 */
class ResetPasswordController extends Controller
{
    use ConsumesResetTokens;

    /**
     * Where the trait would send a browser after a successful reset.
     *
     * Inert in practice, since every reply below is JSON or plain text, but
     * the trait reads the property, so it stays declared.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::CUSTOMER_HOME;

    /**
     * Tokens are checked against the customer broker.
     *
     * @return PasswordBroker
     */
    public function broker()
    {
        return Password::broker('customers');
    }

    /**
     * Store the password chosen by the contact behind the token.
     *
     * Two deliberate departures from the trait. The value is handed over raw,
     * because the model hashes it on assignment; and no session is opened
     * afterwards, so the contact comes back through the login form.
     *
     * @param  mixed  $user  the contact the token was minted for
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
     * Confirm that the token was spent and the password replaced.
     *
     * @param  string  $response
     */
    protected function sendResetResponse(Request $request, $response)
    {
        return response()->json([
            'message' => 'Password reset successfully.',
        ]);
    }

    /**
     * Refuse a token that was missing, expired, mismatched or forged.
     *
     * @param  string  $response
     */
    protected function sendResetFailedResponse(Request $request, $response)
    {
        return response('Failed, Invalid Token.', Response::HTTP_FORBIDDEN);
    }
}
