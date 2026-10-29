<?php

namespace App\Domains\Receivables\Contracts;

use App\Domains\Receivables\Models\Payment;

interface PaymentPdfDataProvider
{
    public function getPdfData(Payment $payment): mixed;
}
