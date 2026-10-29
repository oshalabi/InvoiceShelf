<?php

namespace App\Domains\Money\Http\Resources\CustomerPortal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The customer-portal view of a currency. Currencies hold nothing private, so
 * the portal serves the same fields the admin API does.
 */
class CurrencyResource extends JsonResource
{
    /**
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'symbol' => $this->symbol,
            'precision' => $this->precision,
            'thousand_separator' => $this->thousand_separator,
            'decimal_separator' => $this->decimal_separator,
            'swap_currency_symbol' => $this->swap_currency_symbol,
            'exchange_rate' => $this->exchange_rate,
        ];
    }
}
