<?php

namespace App\Domains\Accounts\Http\Controllers\Auth;

use App\Platform\Http\Controller;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails as IssuesResetLinks;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Mails a staff account a link for choosing a new password.
 *
 * The imported trait drives the whole flow: validate the address, ask a
 * broker for a link, branch on what the broker says. Only the two replies are
 * swapped out below, because the admin SPA expects JSON rather than a redirect
 * back to a Blade form. The broker is left alone, so links are minted by the
 * default `users` broker against the staff table.
 *
 * Request volume is capped at the route, not here: the endpoint is registered
 * behind a ten-per-two-minutes throttle.
 */
class ForgotPasswordController extends Controller
{
    use IssuesResetLinks;

    /**
     * Every reason a link might not go out collapses into this one sentence.
     */
    private const UNDELIVERABLE = 'Email could not be sent to this email address.';

    /**
     * Confirm that a link reached the mailer.
     *
     * The broker's own status key rides along in `data`, which is what the
     * SPA logs when a send is investigated after the fact.
     *
     * @param  string  $response
     */
    protected function sendResetLinkResponse(Request $request, $response)
    {
        return response()->json(['message' => 'Password reset email sent.', 'data' => $response]);
    }

    /**
     * Report that no link went out.
     *
     * Quirk kept as is: this refusal is a probing oracle. An address nobody
     * holds fails here while a known address succeeds, so the difference
     * between 403 and 200 tells a caller which staff addresses exist. The
     * throttle on the route is the only thing narrowing that.
     *
     * @param  string  $response
     */
    protected function sendResetLinkFailedResponse(Request $request, $response)
    {
        return response()->json(['error' => self::UNDELIVERABLE], Response::HTTP_FORBIDDEN);
    }
}
