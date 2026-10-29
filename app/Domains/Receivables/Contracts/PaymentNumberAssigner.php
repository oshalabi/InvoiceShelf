<?php

namespace App\Domains\Receivables\Contracts;

use App\Domains\Receivables\Data\PaymentNumberAssignment;
use App\Domains\Receivables\Models\Payment;

interface PaymentNumberAssigner
{
    public function next(
        Payment $payment,
        int $companyId,
        int $customerId,
        bool $generateNumber = false,
    ): PaymentNumberAssignment;
}
