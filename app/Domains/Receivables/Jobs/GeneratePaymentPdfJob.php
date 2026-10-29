<?php

namespace App\Domains\Receivables\Jobs;

use App\Domains\Receivables\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Renders a receipt away from the request that caused it.
 *
 * The stored file is named for the receipt number, so a save that could have
 * moved that number asks for the old file to be dropped first; leaving it
 * would strand a stale twin beside the new one.
 */
class GeneratePaymentPdfJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $payment;

    public $deleteExistingFile;

    /**
     * @param  Payment  $payment
     * @param  bool  $deleteExistingFile  discard the previously stored file
     */
    public function __construct(
        $payment,
        $deleteExistingFile = false
    ) {
        $this->payment = $payment;
        $this->deleteExistingFile = $deleteExistingFile;
    }

    /**
     * Hands the rendering to the receipt itself and always reports success.
     * The number handed back is a leftover of an older queue contract —
     * nothing downstream reads it.
     */
    public function handle(): int
    {
        $receipt = $this->payment;

        $receipt->generatePDF('payment', $receipt->payment_number, $this->deleteExistingFile);

        return 0;
    }
}
