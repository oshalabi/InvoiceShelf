<?php

namespace App\Domains\Money\Http\Resources;

use App\Domains\Accounts\Http\Resources\CompanyResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExchangeRateProviderResource extends JsonResource
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'driver' => $this->driver,
            'currencies' => $this->currencies,
            'driver_config' => $this->driver_config,
            'company_id' => $this->company_id,
            'active' => $this->active,
            'company' => $this->when(
                $this->company()->exists(),
                fn (): CompanyResource => new CompanyResource($this->company)
            ),
        ];
    }
}
