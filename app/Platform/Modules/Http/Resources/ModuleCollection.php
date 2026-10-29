<?php

namespace App\Platform\Modules\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ModuleCollection extends ResourceCollection
{
    /**
     * Hand back the default mapping over the wrapped module resources.
     *
     * @param  Request  $request
     * @return array|Arrayable|\JsonSerializable
     */
    public function toArray($request): array
    {
        $modules = parent::toArray($request);

        return $modules;
    }
}
