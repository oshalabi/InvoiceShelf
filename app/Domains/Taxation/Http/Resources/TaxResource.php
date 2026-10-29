<?php

namespace App\Domains\Taxation\Http\Resources;

use App\Domains\Money\Http\Resources\CurrencyResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin payload for a tax applied to a document.
 *
 * Alongside the snapshotted figures it reports every owner id -- consumers
 * decide which one is populated -- and `type`, the kind of the originating tax
 * type, which is read straight off the relation.
 */
class TaxResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tax_type_id' => $this->tax_type_id,
            'invoice_id' => $this->invoice_id,
            'estimate_id' => $this->estimate_id,
            'invoice_item_id' => $this->invoice_item_id,
            'estimate_item_id' => $this->estimate_item_id,
            'expense_id' => $this->expense_id,
            'item_id' => $this->item_id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'amount' => $this->amount,
            'percent' => $this->percent,
            'calculation_type' => $this->calculation_type,
            'fixed_amount' => $this->fixed_amount,
            'compound_tax' => $this->compound_tax,
            'base_amount' => $this->base_amount,
            'currency_id' => $this->currency_id,
            // Dereferenced without a guard: a tax type with rows attached
            // cannot be deleted, so the parent is always there.
            'type' => $this->taxType->type,
            'recurring_invoice_id' => $this->recurring_invoice_id,
            'tax_type' => $this->when(
                $this->taxType()->exists(),
                fn () => new TaxTypeResource($this->taxType)
            ),
            'currency' => $this->when(
                $this->currency()->exists(),
                fn () => new CurrencyResource($this->currency)
            ),
        ];
    }
}
