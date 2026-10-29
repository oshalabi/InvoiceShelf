<?php

namespace App\Platform\Operations\Installation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the wizard's domain step, which only ever carries the host the
 * instance will be served from.
 */
class DomainEnvironmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'app_domain' => [
                'required',
            ],
        ];
    }
}
