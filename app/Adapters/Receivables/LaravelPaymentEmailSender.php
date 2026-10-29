<?php

namespace App\Adapters\Receivables;

use App\Domains\Receivables\Contracts\PaymentEmailSender;
use App\Domains\Receivables\Mail\SendPaymentMail;
use Illuminate\Support\Facades\Mail;

class LaravelPaymentEmailSender implements PaymentEmailSender
{
    public function send(array $data): void
    {
        $mail = Mail::to($data['to']);

        if (! empty($data['cc'])) {
            $mail->cc($data['cc']);
        }

        if (! empty($data['bcc'])) {
            $mail->bcc($data['bcc']);
        }

        $mail->send(new SendPaymentMail($data));
    }
}
