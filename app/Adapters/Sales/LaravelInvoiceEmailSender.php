<?php

namespace App\Adapters\Sales;

use App\Domains\Sales\Contracts\InvoiceEmailSender;
use App\Domains\Sales\Mail\SendCreditNoteMail;
use App\Domains\Sales\Mail\SendInvoiceMail;
use Illuminate\Support\Facades\Mail;

class LaravelInvoiceEmailSender implements InvoiceEmailSender
{
    public function send(array $data, bool $creditNote): void
    {
        $mail = Mail::to($data['to']);

        if (! empty($data['cc'])) {
            $mail->cc($data['cc']);
        }

        if (! empty($data['bcc'])) {
            $mail->bcc($data['bcc']);
        }

        $mail->send($creditNote
            ? new SendCreditNoteMail($data)
            : new SendInvoiceMail($data));
    }
}
