<?php

namespace App\Domains\Taxation\Http\Resources\CustomerPortal;

use App\Domains\Accounts\Http\Resources\CustomerPortal\CompanyResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tax type as the customer portal sees it: enough to label a tax line, without
 * the bookkeeping detail. Deliberately narrower than the admin payload -- the
 * calculation type, the fixed amount and the row kind are all withheld.
 */
class TaxTypeResource extends JsonResource
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
            'name' => $this->name,
            'percent' => $this->percent,
            'transaction_type' => $this->transaction_type,
            'compound_tax' => $this->compound_tax,
            'collective_tax' => $this->collective_tax,
            'description' => $this->description,
            'company_id' => $this->company_id,
            'company' => $this->when(
                $this->company()->exists(),
                fn () => new CompanyResource($this->company)
            ),
        ];
    }
}
