<?php

namespace App\Domains\Accounts\Http\Resources\CustomerPortal;

use App\Domains\Money\Http\Resources\CustomerPortal\CurrencyResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A staff account as the customer portal publishes it.
 *
 * The same profile fields as the admin view, minus the platform-administrator
 * flag: the portal only ever needs to know whether the account owns the company
 * it is looking at. Roles, the company-formatted creation date, and the currency
 * and companies when those exist, are carried along as well.
 */
class UserResource extends JsonResource
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        $user = $this->resource;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'contact_name' => $user->contact_name,
            'company_name' => $user->company_name,
            'website' => $user->website,
            'enable_portal' => $user->enable_portal,
            'currency_id' => $user->currency_id,
            'facebook_id' => $user->facebook_id,
            'google_id' => $user->google_id,
            'github_id' => $user->github_id,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'avatar' => $user->avatar,
            'is_owner' => $user->isOwner(),
            'roles' => $user->roles,
            'formatted_created_at' => $user->formattedCreatedAt,
            'currency' => $this->when(
                $user->currency()->exists(),
                fn () => new CurrencyResource($user->currency)
            ),
            'companies' => $this->when(
                $user->companies()->exists(),
                fn () => CompanyResource::collection($user->companies)
            ),
        ];
    }
}
