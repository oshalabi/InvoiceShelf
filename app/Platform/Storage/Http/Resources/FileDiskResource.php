<?php

namespace App\Platform\Storage\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A registered storage target as the admin API publishes it.
 *
 * Everything the disks screen needs to draw a row and reopen the edit form:
 * the label an operator picked, whether the row is one of the two seeded
 * system disks or one they added, which driver backs it, and whether it is the
 * one new uploads currently land on.
 *
 * `credentials` travels as the raw column value -- a JSON string, not a decoded
 * object -- so secrets such as an S3 secret key or a Dropbox token are handed
 * back verbatim to anyone allowed to read the listing. That is the established
 * payload and the edit form depends on it, so it is kept; the ability gate in
 * front of every endpoint is the only thing keeping those values in.
 *
 * `company_id` is stamped from the request header at creation time and is never
 * used to narrow anything afterwards, which is why a disk created inside one
 * workspace still shows up for every other one.
 */
class FileDiskResource extends JsonResource
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        $fileDisk = $this->resource;

        return [
            'id' => $fileDisk->id,
            'name' => $fileDisk->name,
            'type' => $fileDisk->type,
            'driver' => $fileDisk->driver,
            'set_as_default' => $fileDisk->set_as_default,
            'credentials' => $fileDisk->credentials,
            'company_id' => $fileDisk->company_id,
        ];
    }
}
