<?php

namespace App\Domains\Purchases\Contracts;

use App\Domains\Purchases\Models\Expense;

interface ExpenseTaxManager
{
    /** @param array<int, array{tax_type_id: int, amount: int}> $taxes */
    public function replace(Expense $expense, array $taxes): void;

    public function clear(Expense $expense): void;
}
