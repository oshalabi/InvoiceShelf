<?php

namespace App\Domains\Receivables\Http\Resources;

use App\Domains\Accounts\Http\Resources\CompanyResource;
use App\Domains\Sales\Http\Resources\InvoiceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One online-payment attempt against an invoice, as the admin API publishes it.
 *
 * Gateway modules own the attempt itself; what travels here is the audit trail
 * a human reads -- the gateway's own reference, which gateway it was, whether
 * the attempt succeeded, when it happened, and the invoice it was aimed at.
 * The amount and the public hash are not part of this payload.
 *
 * Both associations are gated on an existence probe against the database, so
 * they are correct whether or not anything was eager loaded and cost a query
 * each per serialised row.
 */
class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        $transaction = $this->resource;

        return [
            'id' => $transaction->id,
            'transaction_id' => $transaction->transaction_id,
            'type' => $transaction->type,
            'status' => $transaction->status,
            'transaction_date' => $transaction->transaction_date,
            'invoice_id' => $transaction->invoice_id,
            'invoice' => $this->when(
                $transaction->invoice()->exists(),
                fn () => new InvoiceResource($transaction->invoice)
            ),
            'company' => $this->when(
                $transaction->company()->exists(),
                fn () => new CompanyResource($transaction->company)
            ),
        ];
    }
}
