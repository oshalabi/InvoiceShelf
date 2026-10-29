<?php

namespace App\Domains\Sales\Contracts;

use App\Domains\Sales\Models\Invoice;

interface InvoicePdfDataProvider
{
    public function getPdfData(Invoice $invoice): mixed;
}
