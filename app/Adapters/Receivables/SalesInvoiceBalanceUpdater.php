<?php

namespace App\Adapters\Receivables;

use App\Domains\Receivables\Contracts\InvoiceBalanceUpdater;
use App\Domains\Sales\Application\InvoiceBalanceService;
use App\Domains\Sales\Models\Invoice;

class SalesInvoiceBalanceUpdater implements InvoiceBalanceUpdater
{
    public function __construct(
        private readonly InvoiceBalanceService $invoiceBalanceService,
    ) {}

    public function creditedTotal(Invoice $invoice): int
    {
        return $this->invoiceBalanceService->creditedTotal($invoice);
    }

    public function recalculate(Invoice $invoice): void
    {
        $this->invoiceBalanceService->recalculate($invoice);
    }
}
