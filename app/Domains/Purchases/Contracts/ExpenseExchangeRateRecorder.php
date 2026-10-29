<?php

namespace App\Domains\Purchases\Contracts;

use App\Domains\Purchases\Models\Expense;

interface ExpenseExchangeRateRecorder
{
    public function record(Expense $expense): void;
}
