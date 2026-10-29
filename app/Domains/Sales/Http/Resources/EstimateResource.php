<?php

namespace App\Domains\Sales\Http\Resources;

use App\Domains\Accounts\Http\Resources\CompanyResource;
use App\Domains\Accounts\Http\Resources\UserResource;
use App\Domains\Contacts\Http\Resources\CustomerResource;
use App\Domains\Metadata\Http\Resources\CustomFieldValueResource;
use App\Domains\Money\Http\Resources\CurrencyResource;
use App\Domains\Taxation\Http\Resources\TaxResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An estimate as the admin API publishes it.
 *
 * Carries the stored columns, the dates already rendered in the company's date
 * format, the shareable PDF link, and the notes with their placeholders
 * interpolated rather than the raw template stored on the row.
 *
 * The related records -- lines, contact, author, document taxes, custom field
 * values, company and currency -- are each gated behind an existence probe on
 * the relation, so a missing one leaves its key out of the payload entirely.
 * Every probe is its own query, which is deliberate here: the estimate payload
 * has to answer correctly whether or not the caller eager-loaded anything.
 */
class EstimateResource extends JsonResource
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        $estimate = $this->resource;

        return [
            'id' => $estimate->id,
            'estimate_date' => $estimate->estimate_date,
            'expiry_date' => $estimate->expiry_date,
            'estimate_number' => $estimate->estimate_number,
            'status' => $estimate->status,
            'reference_number' => $estimate->reference_number,
            'tax_per_item' => $estimate->tax_per_item,
            'tax_included' => $estimate->tax_included,
            'discount_per_item' => $estimate->discount_per_item,
            'notes' => $estimate->getNotes(),
            'discount' => $estimate->discount,
            'discount_type' => $estimate->discount_type,
            'discount_val' => $estimate->discount_val,
            'sub_total' => $estimate->sub_total,
            'total' => $estimate->total,
            'tax' => $estimate->tax,
            'unique_hash' => $estimate->unique_hash,
            'creator_id' => $estimate->creator_id,
            'template_name' => $estimate->template_name,
            'customer_id' => $estimate->customer_id,
            'exchange_rate' => $estimate->exchange_rate,
            'base_discount_val' => $estimate->base_discount_val,
            'base_sub_total' => $estimate->base_sub_total,
            'base_total' => $estimate->base_total,
            'base_tax' => $estimate->base_tax,
            'sequence_number' => $estimate->sequence_number,
            'currency_id' => $estimate->currency_id,
            'formatted_expiry_date' => $estimate->formattedExpiryDate,
            'formatted_estimate_date' => $estimate->formattedEstimateDate,
            'estimate_pdf_url' => $estimate->estimatePdfUrl,
            'sales_tax_type' => $estimate->sales_tax_type,
            'sales_tax_address_type' => $estimate->sales_tax_address_type,
            'items' => $this->when(
                $estimate->items()->exists(),
                fn () => EstimateItemResource::collection($estimate->items)
            ),
            'customer' => $this->when(
                $estimate->customer()->exists(),
                fn () => new CustomerResource($estimate->customer)
            ),
            'creator' => $this->when(
                $estimate->creator()->exists(),
                fn () => new UserResource($estimate->creator)
            ),
            'taxes' => $this->when(
                $estimate->taxes()->exists(),
                fn () => TaxResource::collection($estimate->taxes)
            ),
            'fields' => $this->when(
                $estimate->fields()->exists(),
                fn () => CustomFieldValueResource::collection($estimate->fields)
            ),
            'company' => $this->when(
                $estimate->company()->exists(),
                fn () => new CompanyResource($estimate->company)
            ),
            'currency' => $this->when(
                $estimate->currency()->exists(),
                fn () => new CurrencyResource($estimate->currency)
            ),
        ];
    }
}
