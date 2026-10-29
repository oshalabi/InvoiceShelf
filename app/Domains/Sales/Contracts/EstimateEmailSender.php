<?php

namespace App\Domains\Sales\Contracts;

interface EstimateEmailSender
{
    /** @param array<string, mixed> $data */
    public function send(array $data): void;
}
