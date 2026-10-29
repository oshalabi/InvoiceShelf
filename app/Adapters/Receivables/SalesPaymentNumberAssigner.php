<?php

namespace App\Adapters\Receivables;

use App\Domains\Receivables\Contracts\PaymentNumberAssigner;
use App\Domains\Receivables\Data\PaymentNumberAssignment;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Sales\Application\SerialNumberService;

class SalesPaymentNumberAssigner implements PaymentNumberAssigner
{
    public function next(
        Payment $payment,
        int $companyId,
        int $customerId,
        bool $generateNumber = false,
    ): PaymentNumberAssignment {
        $serial = (new SerialNumberService)
            ->setModel($payment)
            ->setCompany($companyId)
            ->setCustomer($customerId)
            ->setModelObject($payment->getKey());

        $number = null;

        if ($generateNumber) {
            $number = $serial->getNextNumber();
        } else {
            $serial->setNextNumbers();
        }

        return new PaymentNumberAssignment(
            $number,
            (int) $serial->nextSequenceNumber,
            (int) $serial->nextCustomerSequenceNumber,
        );
    }
}
