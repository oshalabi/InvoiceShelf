<?php

namespace App\Domains\Sales\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A page of estimates for the admin API.
 *
 * Named after its member resource, so rows are published through
 * EstimateResource and the pagination envelope comes from the framework.
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
