<?php

namespace App\Platform\Storage\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A page of storage targets.
 *
 * Named after its member resource, so rows would be published through
 * FileDiskResource and the pagination envelope would come from the framework.
 * Nothing constructs it today -- the listing endpoint calls
 * FileDiskResource::collection() and gets the framework's anonymous collection
 * instead -- so this class is carried for the sake of the pairing every other
 * resource in the codebase has.
 */
class FileDiskCollection extends ResourceCollection
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return parent::toArray($request);
    }
}
