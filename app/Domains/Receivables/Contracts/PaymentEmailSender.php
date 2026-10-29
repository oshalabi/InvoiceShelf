<?php

namespace App\Domains\Receivables\Contracts;

interface PaymentEmailSender
{
    /** @param array<string, mixed> $data */
    public function send(array $data): void;
}
