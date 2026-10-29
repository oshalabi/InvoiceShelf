<?php

namespace App\Domains\Receivables\Http\Controllers;

use App\Domains\Receivables\Http\Resources\PaymentResource;
use App\Domains\Receivables\Models\Payment;
use App\Platform\Http\Controller;
use App\Platform\Mail\Models\EmailLog;
use Illuminate\Http\Request;

/**
 * Both ends of an emailed receipt link: the rendered file and the JSON the
 * viewer shell reads. Neither asks who is calling — the token in the path is
 * the whole credential — and unlike a billing document, being looked at
 * leaves no mark on a receipt.
 */
class PublicPaymentController extends Controller
{
    /**
     * Stream the receipt the emailed link stands for.
     *
     * The request is taken for the signature's sake and never read.
     */
    public function getPdf(EmailLog $emailLog, Request $request)
    {
        return $this->receiptBehind($emailLog)->getGeneratedPDFOrStream('payment');
    }

    /**
     * Serve the same receipt as JSON.
     */
    public function getPayment(EmailLog $emailLog)
    {
        return PaymentResource::make($this->receiptBehind($emailLog));
    }

    /**
     * Trade an email-log token for the receipt it was minted for.
     *
     * Two things stand between the token and the disclosure, in this order: a
     * log row minted for some other kind of mail is a miss rather than a
     * forbidden read, however the ids happen to line up; and the link must
     * still fall inside the issuing company's expiry window.
     */
    private function receiptBehind(EmailLog $emailLog): Payment
    {
        $receipt = $emailLog->mailable;

        if (! $receipt instanceof Payment) {
            abort(404);
        }

        if ($emailLog->isExpired()) {
            abort(403, 'Link Expired.');
        }

        return $receipt;
    }
}
