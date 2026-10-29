<?php

namespace App\Domains\Receivables\Http\Resources;

use App\Domains\Accounts\Http\Resources\CompanyResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A payment method as the admin API publishes it.
 *
 * Deliberately thin: the label, the company it belongs to, and the kind of
 * method it is -- a manually created one or one registered by a gateway
 * module. The module's own configuration (driver, settings, whether it is
 * active or pointed at a test environment) stays on the server; nothing here
 * exposes it.
 *
 * The owning company is published only when the relation resolves, which is
 * asked of the database each time and so costs a query per serialised row.
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
            'type' => $method->type,
            'company' => $this->when(
                $method->company()->exists(),
                fn () => new CompanyResource($method->company)
            ),
        ];
    }
}
