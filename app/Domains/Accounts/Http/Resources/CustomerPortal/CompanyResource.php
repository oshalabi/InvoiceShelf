<?php

namespace App\Domains\Accounts\Http\Resources\CustomerPortal;

use App\Domains\Contacts\Http\Resources\CustomerPortal\AddressResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The company behind the portal, as the customer portal publishes it.
 *
 * Only what the portal chrome renders: identity, the public handle, both forms
 * of the logo, the owning account's id, and the postal address when one is on
 * file. Nothing about roles or settings is exposed here.
 */
class CompanyResource extends JsonResource
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        $company = $this->resource;

        return [
            'id' => $company->id,
            'name' => $company->name,
            'slug' => $company->slug,
            'logo' => $company->logo,
            'logo_path' => $company->logo_path,
            'unique_hash' => $company->unique_hash,
            'owner_id' => $company->owner_id,
            'address' => $this->when(
                $company->address()->exists(),
                fn () => new AddressResource($company->address)
            ),
        ];
    }
}
