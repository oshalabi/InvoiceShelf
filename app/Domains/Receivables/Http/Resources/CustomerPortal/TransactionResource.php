<?php

namespace App\Domains\Receivables\Http\Resources\CustomerPortal;

use App\Domains\Accounts\Http\Resources\CustomerPortal\CompanyResource;
use App\Domains\Sales\Http\Resources\CustomerPortal\InvoiceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One online-payment attempt against an invoice, as the customer portal
 * publishes it.
 *
 * The same fields the admin payload carries -- the gateway's reference, which
 * gateway it was, the outcome, when it happened and the invoice it was aimed
 * at -- with the nested invoice and company rendered in their portal
 * variants. Neither the amount nor the public hash travels.
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
