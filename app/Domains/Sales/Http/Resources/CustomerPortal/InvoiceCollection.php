<?php

namespace App\Domains\Sales\Http\Resources\CustomerPortal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A page of invoices for the customer portal.
 *
 * Sits in the portal namespace so the member resource derived from this class
 * name is the portal InvoiceResource, not the admin one; the mapping and the
 * pagination envelope are left to the parent.
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
