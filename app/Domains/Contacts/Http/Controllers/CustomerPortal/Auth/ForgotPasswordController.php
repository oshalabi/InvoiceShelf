<?php

namespace App\Domains\Contacts\Http\Controllers\CustomerPortal\Auth;

use App\Platform\Http\Controller;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails as IssuesResetLinks;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Password;

/**
 * Mails a portal contact a link for choosing a new password.
 *
 * The imported trait owns the flow: validate the address, ask a broker for a
 * link, branch on the outcome. Two things are swapped out below: the broker,
 * which has to be the customer one, and both replies, which are shaped for an
 * SPA instead of a redirect back to a Blade form.
 */
class ForgotPasswordController extends Controller
{
    use IssuesResetLinks;

    /**
     * Portal tokens are minted by the customer broker, never the user one.
     *
     * @return PasswordBroker
     */
    public function broker()
    {
        return Password::broker('customers');
    }

    /**
     * Confirm that the link reached the mailer.
     *
     * The broker's own status string is echoed back alongside the message.
     *
     * @param  string  $response
     */
    protected function sendResetLinkResponse(Request $request, $response)
    {
        return response()->json([
            'message' => 'Password reset email sent.',
            'data' => $response,
        ]);
    }

    /**
     * Report that no link went out.
     *
     * Every reason the broker can give (address unknown, request throttled,
     * mail undeliverable) collapses into the same plain-text refusal, in
     * place of the framework's validation error.
     *
     * @param  string  $response
     */
    protected function sendResetLinkFailedResponse(Request $request, $response)
    {
        return response('Email could not be sent to this email address.', Response::HTTP_FORBIDDEN);
    }
}
