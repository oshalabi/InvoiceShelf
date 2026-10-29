<?php

namespace App\Domains\Metadata\Http\Resources;

use App\Domains\Accounts\Http\Resources\CompanyResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A reusable note template as the admin API publishes it.
 *
 * Deliberately thin: which document family the template belongs to, the name
 * an operator picks it out by, the body itself, and whether it is the one
 * offered first for that family. The body travels exactly as stored, markup
 * and all -- nothing is escaped or stripped on the way out, so a consumer
 * that renders it as HTML renders whatever was saved.
 *
 * The owning company is published only when the relation resolves, which is
 * asked of the database every time rather than read off whatever the caller
 * eager loaded, and so costs a query per serialised row plus a second to fetch
 * the row once the probe says there is one. That is the payload's established
 * shape and is kept deliberately.
 */
class NoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        $note = $this->resource;

        return [
            'id' => $note->id,
            'type' => $note->type,
            'name' => $note->name,
            'notes' => $note->notes,
            'is_default' => $note->is_default,
            'company' => $this->when(
                $note->company()->exists(),
                fn () => new CompanyResource($note->company)
            ),
        ];
    }
}
