<?php

namespace App\Domains\Receivables\Http\Resources;

use App\Domains\Accounts\Http\Resources\CompanyResource;
use App\Domains\Contacts\Http\Resources\CustomerResource;
use App\Domains\Metadata\Http\Resources\CustomFieldValueResource;
use App\Domains\Money\Http\Resources\CurrencyResource;
use App\Domains\Sales\Http\Resources\InvoiceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * A received payment as the admin API publishes it.
 *
 * Beyond the stored columns the payload carries three derived groups.
 *
 * The allocation rows come first. They are always published -- there is no
 * gate on them -- because the payment form needs to know which invoices the
 * money is sitting on before it can offer to re-shape it. When the caller has
 * not eager-loaded them they are fetched here, together with their invoices,
 * which is a query per serialised row; listings that care should load
 * `allocations.invoice` up front.
 *
 * From those rows come the four settlement figures: how much of the payment is
 * spoken for and how much is still free, once in the customer's currency and
 * once in the company's. The company-currency side needs a base amount even for
 * rows that never stored one, so a missing `base_amount` is derived from the
 * amount and the exchange rate, with a rate of zero read as one rather than
 * collapsing the figure to nothing.
 *
 * The trailing associations are each gated on an existence probe against the
 * database, so they are correct whether or not anything was eager loaded and
 * cost one query apiece per serialised row. That is the established shape of
 * this payload and is kept deliberately.
 */
class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        $payment = $this->resource;

        $allocations = $payment->relationLoaded('allocations')
            ? $payment->allocations
            : $payment->allocations()->with('invoice')->get();

        $allocated = (int) $allocations->sum('amount');
        $baseAllocated = (int) $allocations->sum('base_amount');
        $baseAmount = $this->baseAmount();

        return [
            'id' => $payment->id,
            'payment_number' => $payment->payment_number,
            'payment_date' => $payment->payment_date,
            'notes' => $payment->getNotes(),
            'amount' => $payment->amount,
            'unique_hash' => $payment->unique_hash,
            'company_id' => $payment->company_id,
            'payment_method_id' => $payment->payment_method_id,
            'creator_id' => $payment->creator_id,
            'customer_id' => $payment->customer_id,
            'exchange_rate' => $payment->exchange_rate,
            'base_amount' => $baseAmount,
            'allocations' => $this->allocationRows($allocations),
            'allocated_amount' => $allocated,
            'unallocated_amount' => (int) $payment->amount - $allocated,
            'base_allocated_amount' => $baseAllocated,
            'base_unallocated_amount' => $baseAmount - $baseAllocated,
            'currency_id' => $payment->currency_id,
            'transaction_id' => $payment->transaction_id,
            'sequence_number' => $payment->sequence_number,
            'formatted_created_at' => $payment->formattedCreatedAt,
            'formatted_payment_date' => $payment->formattedPaymentDate,
            'payment_pdf_url' => $payment->paymentPdfUrl,
            'customer' => $this->when(
                $payment->customer()->exists(),
                fn () => new CustomerResource($payment->customer)
            ),
            'payment_method' => $this->when(
                $payment->paymentMethod()->exists(),
                fn () => new PaymentMethodResource($payment->paymentMethod)
            ),
            'fields' => $this->when(
                $payment->fields()->exists(),
                fn () => CustomFieldValueResource::collection($payment->fields)
            ),
            'company' => $this->when(
                $payment->company()->exists(),
                fn () => new CompanyResource($payment->company)
            ),
            'currency' => $this->when(
                $payment->currency()->exists(),
                fn () => new CurrencyResource($payment->currency)
            ),
            'transaction' => $this->when(
                $payment->transaction()->exists(),
                fn () => new TransactionResource($payment->transaction)
            ),
        ];
    }

    /**
     * The payment's value in the company's currency.
     *
     * Older rows predate the stored column, so when it is absent the figure is
     * recomputed from the amount and the rate. A rate of zero -- or any other
     * falsy value -- stands in as one, which keeps such a payment at its face
     * value instead of reporting it as worth nothing.
     */
    private function baseAmount(): int
    {
        $payment = $this->resource;

        if ($payment->base_amount === null) {
            return (int) round($payment->amount * ($payment->exchange_rate ?: 1));
        }

        return (int) $payment->base_amount;
    }

    /**
     * One row per invoice this payment has been allocated against.
     *
     * The invoice is nested in full where there is one; an allocation whose
     * invoice cannot be resolved still reports its amounts.
     */
    private function allocationRows(Collection $allocations): Collection
    {
        return $allocations->map(fn ($allocation) => [
            'id' => $allocation->id,
            'invoice_id' => $allocation->invoice_id,
            'amount' => $allocation->amount,
            'base_amount' => $allocation->base_amount,
            'invoice' => $allocation->invoice ? new InvoiceResource($allocation->invoice) : null,
        ]);
    }
}
