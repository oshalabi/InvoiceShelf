<?php

namespace App\Domains\Purchases\Contracts;

use App\Domains\Purchases\Data\PendingExpenseReceipt;
use App\Domains\Purchases\Data\StoredExpenseReceipt;
use App\Domains\Purchases\Models\Expense;

interface ExpenseReceiptManager
{
    public function attach(Expense $expense, PendingExpenseReceipt $receipt): void;

    public function replace(Expense $expense, PendingExpenseReceipt $receipt): void;

    public function attachBase64(
        Expense $expense,
        string $contents,
        string $fileName,
        bool $replaceExisting,
    ): void;

    public function clear(Expense $expense): void;

    public function first(Expense $expense): ?StoredExpenseReceipt;
}
