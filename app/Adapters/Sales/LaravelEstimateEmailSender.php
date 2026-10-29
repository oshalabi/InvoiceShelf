<?php

namespace App\Adapters\Sales;

use App\Domains\Sales\Contracts\EstimateEmailSender;
use App\Domains\Sales\Mail\SendEstimateMail;
use Illuminate\Support\Facades\Mail;

class LaravelEstimateEmailSender implements EstimateEmailSender
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

        $mail->send(new SendEstimateMail($data));
    }
}
