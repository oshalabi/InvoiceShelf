<?php

namespace App\Domains\Sales\Http\Resources\CustomerPortal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A page of invoice lines for the customer portal.
 *
 * Namespace and class name together select the portal InvoiceItemResource as
 * the member resource.
 */
class InvoiceItemCollection extends ResourceCollection
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return parent::toArray($request);
    }
}
