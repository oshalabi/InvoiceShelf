<?php

namespace App\Domains\Purchases\Http\Resources;

use App\Domains\Accounts\Http\Resources\CompanyResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A spending bucket as the admin API publishes it.
 *
 * A category owns very little: a label, an optional blurb, and the company it
 * belongs to. The two remaining fields are worked out at read time rather than
 * stored -- `amount` totals every expense filed under the category, and the
 * creation date is rendered through the owning company's date format. Each is
 * a trip to the database per serialised row, so a page of categories pays for
 * them once per row.
 *
 * The owning company is published only when the relation resolves, and that is
 * asked of the database every time rather than read off whatever the caller
 * eager loaded -- another query per row. This is the payload's established
 * shape and is kept deliberately.
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
