<?php

namespace App\Domains\Receivables\Console;

use App\Domains\Receivables\Application\PaymentAllocationService;
use App\Domains\Receivables\Contracts\InvoiceBalanceUpdater;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Receivables\Models\PaymentAllocation;
use App\Domains\Sales\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Re-attach the payments the 3.x upgrade could not carry over.
 *
 * The allocation migration refuses to allocate a payment to a draft invoice,
 * because a draft owes nothing. Rather than lose the association it files it in
 * `legacy_payment_links` and leaves the money as unapplied customer credit.
 * This command walks that file: for every payment whose invoice has since been
 * issued it applies the credit and forgets the link, and for every one still
 * waiting on a draft it leaves both exactly where they are, so it can be run
 * as often as the operator likes.
 *
 * Links filed as a mismatch — a missing invoice, a credit note, a target
 * belonging to another company, contact or currency — are never repaired here.
 * They are reported so somebody can decide what the association ought to have
 * been, and that is all.
 */
class RestoreLegacyPaymentLinks extends Command
{
    protected $signature = 'payments:restore-legacy-links {--dry-run : Report what would be restored without writing}';

    protected $description = 'Apply payments the upgrade parked as unapplied credit to the invoices that have since been issued';

    private const OUTCOME_RESTORED = 'restored';

    private const OUTCOME_WAITING = 'waiting-on-draft';

    private const OUTCOME_SKIPPED = 'skipped';

    private const OUTCOME_MISMATCH = 'mismatch-retained';

    /**
     * Report on, and unless asked not to, repair every restorable legacy link.
     */
    public function handle(PaymentAllocationService $allocationService, InvoiceBalanceUpdater $balances): int
    {
        if (! Schema::hasTable('legacy_payment_links')) {
            $this->info('No legacy payment links were recorded by the upgrade.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run: nothing below is written.');
        }

        $rows = [];

        // What an earlier link in this run would have taken out of an invoice,
        // so a dry run does not promise the same balance to two payments.
        $reserved = [];

        foreach ($this->links('draft') as $link) {
            $payment = Payment::query()->find($link->payment_id);
            $invoice = Invoice::query()->find($link->invoice_id);

            if (! $payment || ! $invoice) {
                $rows[] = $this->row($link, null, self::OUTCOME_SKIPPED, $payment
                    ? 'invoice no longer exists'
                    : 'payment no longer exists');

                continue;
            }

            if ($invoice->status === Invoice::STATUS_DRAFT) {
                $rows[] = $this->row($link, null, self::OUTCOME_WAITING, 'invoice is still a draft');

                continue;
            }

            $unallocated = (int) $payment->amount - (int) $payment->allocations()->sum('amount');

            if ($unallocated < 1) {
                $rows[] = $this->row($link, 0, self::OUTCOME_SKIPPED, 'no unallocated credit');

                continue;
            }

            $available = $this->availableBalance($invoice, $payment, $balances) - ($reserved[$invoice->id] ?? 0);

            if ($available < 1) {
                $rows[] = $this->row($link, 0, self::OUTCOME_SKIPPED, 'no invoice balance available');

                continue;
            }

            $applicable = min($unallocated, $available);

            if ($dryRun) {
                $reserved[$invoice->id] = ($reserved[$invoice->id] ?? 0) + $applicable;
                $rows[] = $this->row($link, $applicable, self::OUTCOME_RESTORED, 'would apply the credit');

                continue;
            }

            try {
                $allocationService->applyCustomerCredits((int) $payment->company_id, (int) $payment->customer_id, [[
                    'payment_id' => (int) $payment->id,
                    'invoice_id' => (int) $invoice->id,
                    'amount' => $applicable,
                ]]);
            } catch (ValidationException $exception) {
                $rows[] = $this->row($link, $applicable, self::OUTCOME_SKIPPED, $this->refusal($exception));

                continue;
            }

            DB::table('legacy_payment_links')->where('id', $link->id)->delete();

            $rows[] = $this->row($link, $applicable, self::OUTCOME_RESTORED, 'credit applied');
        }

        foreach ($this->links('mismatch') as $link) {
            $rows[] = $this->row($link, null, self::OUTCOME_MISMATCH, 'kept for reference; never repaired automatically');
        }

        $this->report($rows);

        return self::SUCCESS;
    }

    /**
     * The recorded links of one kind, oldest first.
     */
    private function links(string $reason): Collection
    {
        return DB::table('legacy_payment_links')
            ->where('reason', $reason)
            ->orderBy('id')
            ->get();
    }

    /**
     * What the invoice can still take from this payment.
     *
     * The same arithmetic the allocation service guards with: the total less
     * the credit notes written against it, less whatever other payments already
     * cover. This payment's own allocations are deliberately not subtracted —
     * applying credit replaces its allocation set rather than adding to it.
     */
    private function availableBalance(Invoice $invoice, Payment $payment, InvoiceBalanceUpdater $balances): int
    {
        $allocatedByOthers = (int) PaymentAllocation::query()
            ->where('invoice_id', $invoice->id)
            ->where('payment_id', '!=', $payment->id)
            ->sum('amount');

        return max(0, (int) $invoice->total - $balances->creditedTotal($invoice) - $allocatedByOthers);
    }

    /**
     * The service's refusal, flattened into one readable line.
     */
    private function refusal(ValidationException $exception): string
    {
        return collect($exception->errors())->flatten()->implode('; ');
    }

    /**
     * One line of the report.
     */
    private function row(object $link, ?int $amount, string $outcome, string $detail): array
    {
        return [
            'payment_id' => (int) $link->payment_id,
            'invoice_id' => (int) $link->invoice_id,
            'amount' => $amount === null ? '-' : (string) $amount,
            'outcome' => $outcome,
            'detail' => $detail,
        ];
    }

    /**
     * Print the table and the tally underneath it.
     */
    private function report(array $rows): void
    {
        if ($rows === []) {
            $this->info('No legacy payment links are waiting to be restored.');

            return;
        }

        $this->table(['Payment', 'Invoice', 'Amount', 'Outcome', 'Detail'], $rows);

        $counts = collect($rows)->countBy('outcome');

        foreach ([
            self::OUTCOME_RESTORED,
            self::OUTCOME_WAITING,
            self::OUTCOME_SKIPPED,
            self::OUTCOME_MISMATCH,
        ] as $outcome) {
            $this->line(sprintf('%-18s %d', $outcome.':', $counts->get($outcome, 0)));
        }
    }
}
