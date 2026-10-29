<?php

namespace App\Domains\Purchases\Application;

use App\Domains\Purchases\Contracts\ExpenseTaxManager;
use App\Domains\Purchases\Models\Expense;

class ClearExpenseTaxes
{
    public function __construct(
        private readonly ExpenseTaxManager $expenseTaxManager,
    ) {}

    public function deleting(Expense $expense): void
    {
        $this->expenseTaxManager->clear($expense);
    }
}
