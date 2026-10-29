<?php

namespace App\Domains\Money\Http\Requests;

use App\Rules\PublicHttpUrl;
use Illuminate\Foundation\Http\FormRequest;

class ExchangeRateProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'driver' => ['required'],
            'key' => ['required'],
            'currencies' => ['nullable'],
            'currencies.*' => ['nullable'],
            'driver_config' => ['nullable'],

            // A dedicated CurrencyConverter plan lets the operator name the
            // endpoint we then call with their key: keep it off private and
            // otherwise non-routable hosts on top of the syntax check.
            'driver_config.url' => ['nullable', 'string', 'url', new PublicHttpUrl],

            'active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Validated attributes with the company taken from the request context —
     * a client-supplied company_id is never honoured.
     */
    public function getExchangeRateProviderPayload()
    {
        return collect($this->validated())
            ->merge(['company_id' => $this->header('company')])
            ->toArray();
    }
}
