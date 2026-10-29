<?php

namespace App\Domains\Receivables\Http\Resources\CustomerPortal;

use App\Domains\Accounts\Http\Resources\CustomerPortal\CompanyResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A payment method as the customer portal publishes it.
 *
 * The label and its owning company, nothing else. Unlike the admin payload
 * this one withholds the kind of method it is, so a portal reader cannot tell
 * a manually created label from one a gateway module registered.
 *
 * The company is published only when the relation resolves, which is asked of
 * the database each time and so costs a query per serialised row.
 */
class PaymentMethodResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        $method = $this->resource;

        return [
            'id' => $method->id,
            'name' => $method->name,
            'company_id' => $method->company_id,
            'company' => $this->when(
                $method->company()->exists(),
                fn () => new CompanyResource($method->company)
            ),
        ];
    }
}
