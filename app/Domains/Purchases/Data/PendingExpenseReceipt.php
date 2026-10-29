<?php

namespace App\Domains\Purchases\Data;

final readonly class PendingExpenseReceipt
{
    public function __construct(
        public string $path,
        public string $fileName,
    ) {}
}
