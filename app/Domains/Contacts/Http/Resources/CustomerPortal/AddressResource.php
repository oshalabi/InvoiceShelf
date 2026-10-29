<?php

namespace App\Domains\Contacts\Http\Resources\CustomerPortal;

use App\Domains\Accounts\Http\Resources\CustomerPortal\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A postal address as the customer portal publishes it: the stored columns,
 * plus the country and the owning user when either of them is on file.
 */
class AddressResource extends JsonResource
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address_street_1' => $this->address_street_1,
            'address_street_2' => $this->address_street_2,
            'city' => $this->city,
            'state' => $this->state,
            'country_id' => $this->country_id,
            'zip' => $this->zip,
            'phone' => $this->phone,
            'fax' => $this->fax,
            'type' => $this->type,
            'user_id' => $this->user_id,
            'company_id' => $this->company_id,
            'customer_id' => $this->customer_id,
            'country' => $this->when(
                $this->country()->exists(),
                fn () => new CountryResource($this->country)
            ),
            'user' => $this->when(
                $this->user()->exists(),
                fn () => new UserResource($this->user)
            ),
        ];
    }
}
