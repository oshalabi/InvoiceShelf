<?php

namespace App\Domains\Purchases\Data;

final readonly class StoredExpenseReceipt
{
    public function __construct(
        public string $path,
        public string $fileName,
    ) {}
}
