<?php

namespace App\Domains\Receivables\Contracts;

use App\Domains\Sales\Models\Invoice;

interface InvoiceBalanceUpdater
{
    public function creditedTotal(Invoice $invoice): int;

    public function recalculate(Invoice $invoice): void;
}
