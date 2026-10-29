<?php

namespace App\Platform\Modules\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UninstallMarketplaceModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|\Stringable>> */
    public function rules(): array
    {
        return [
            'remove_data' => ['required', 'boolean'],
            'confirmation' => [
                'nullable',
                'string',
                'max:100',
                'required_if:remove_data,true',
                Rule::in([(string) $this->route('module')]),
            ],
        ];
    }
}
