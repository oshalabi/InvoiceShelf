<?php

namespace App\Domains\Taxation\Http\Resources\CustomerPortal;

use App\Domains\Money\Http\Resources\CustomerPortal\CurrencyResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Applied tax as the customer portal sees it.
 *
 * Trimmed against the admin payload: no expense owner (the portal never shows
 * purchases), no calculation type or fixed amount, and no tax-type kind.
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
            'item_id' => $this->item_id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'amount' => $this->amount,
            'percent' => $this->percent,
            'compound_tax' => $this->compound_tax,
            'base_amount' => $this->base_amount,
            'currency_id' => $this->currency_id,
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
