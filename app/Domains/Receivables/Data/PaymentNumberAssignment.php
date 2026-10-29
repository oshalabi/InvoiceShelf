<?php

namespace App\Domains\Receivables\Data;

final readonly class PaymentNumberAssignment
{
    public function __construct(
        public ?string $number,
        public int $sequence,
        public int $customerSequence,
    ) {}
}
