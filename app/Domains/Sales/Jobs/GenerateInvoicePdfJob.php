<?php

namespace App\Domains\Sales\Jobs;

use App\Domains\Sales\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Renders an invoice's PDF off the request thread.
 *
 * The stored file is named after the document number, so anything that can
 * change that number — an edit, a re-render after crediting — asks for the
 * previous file to be dropped first rather than leaving a stale twin behind.
 */
class GenerateInvoicePdfJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $invoice;

    public $deleteExistingFile;

    /**
     * @param  Invoice  $invoice
     * @param  bool  $deleteExistingFile  drop the previously stored file first
     */
    public function __construct(
        $invoice,
        $deleteExistingFile = false
    ) {
        $this->invoice = $invoice;
        $this->deleteExistingFile = $deleteExistingFile;
    }

    /**
     * Hands the work to the document itself and always reports success — the
     * return value is a leftover of the queue contract; nothing reads it.
     */
    public function handle(): int
    {
        $document = $this->invoice;

        $document->generatePDF('invoice', $document->invoice_number, $this->deleteExistingFile);

        return 0;
    }
}
