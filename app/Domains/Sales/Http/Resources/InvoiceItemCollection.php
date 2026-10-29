<?php

namespace App\Domains\Sales\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A page of invoice lines for the admin API.
 *
 * Named after its member resource, so rows are published through
 * InvoiceItemResource without the wrapper having to say so explicitly.
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
