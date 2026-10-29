<?php

namespace App\Domains\Sales\Http\Resources\CustomerPortal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A page of estimates for the customer portal.
 *
 * Namespace and class name together select the portal EstimateResource as the
 * member resource; the pagination envelope comes from the framework.
 */
class EstimateCollection extends ResourceCollection
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return parent::toArray($request);
    }
}
