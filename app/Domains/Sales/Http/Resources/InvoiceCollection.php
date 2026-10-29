<?php

namespace App\Domains\Sales\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A page of invoices for the admin API.
 *
 * A named wrapper rather than an anonymous collection: the member resource is
 * derived from this class name, so every row is published through
 * InvoiceResource and the pagination envelope is added by the framework. The
 * mapping itself is left to the parent -- this type exists to name the payload.
 */
class InvoiceCollection extends ResourceCollection
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return parent::toArray($request);
    }
}
