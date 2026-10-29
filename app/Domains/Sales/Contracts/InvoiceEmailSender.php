<?php

namespace App\Domains\Sales\Contracts;

interface InvoiceEmailSender
{
    /** @param array<string, mixed> $data */
    public function send(array $data, bool $creditNote): void;
}
