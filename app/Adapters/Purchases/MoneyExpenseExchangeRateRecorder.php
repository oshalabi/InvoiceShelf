<?php

namespace App\Adapters\Purchases;

use App\Domains\Money\Models\ExchangeRateLog;
use App\Domains\Purchases\Contracts\ExpenseExchangeRateRecorder;
use App\Domains\Purchases\Models\Expense;

class MoneyExpenseExchangeRateRecorder implements ExpenseExchangeRateRecorder
{
    public function record(Expense $expense): void
    {
        ExchangeRateLog::addExchangeRateLog(model: $expense);
    }
}
