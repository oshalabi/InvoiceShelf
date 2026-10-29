<?php

namespace App\Domains\Receivables\Contracts;

use App\Domains\Receivables\Models\Payment;

interface PaymentExchangeRateRecorder
{
    public function record(Payment $payment): void;
}
