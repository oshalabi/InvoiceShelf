<?php

namespace App\Domains\Purchases\Http\Resources\CustomerPortal;

use App\Domains\Accounts\Http\Resources\CustomerPortal\CompanyResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A spending bucket as the customer portal publishes it.
 *
 * Field for field this matches the admin payload -- the label, the blurb, the
 * owning company, the read-time total of everything filed under the category
 * and the company-formatted creation date. What differs is the company nested
 * underneath, which is the portal's narrower view of the business rather than
 * the administrative one.
 *
 * The derived total, the formatted date and the existence probe behind the
 * nested company each reach for the database on every serialised row. That is
 * the payload's established shape and is kept deliberately.
 */
class ExpenseCategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        $category = $this->resource;

        return [
            'id' => $category->id,
            'name' => $category->name,
            'description' => $category->description,
            'company_id' => $category->company_id,
            'amount' => $category->amount,
            'formatted_created_at' => $category->formattedCreatedAt,
            'company' => $this->when(
                $category->company()->exists(),
                fn () => new CompanyResource($category->company)
            ),
        ];
    }
}
