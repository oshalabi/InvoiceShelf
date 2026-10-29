<?php

namespace App\Domains\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Payload of the bulk estimate removal endpoint: a list of ids, each of which
 * has to be an estimate. Company scoping is applied by the controller when it
 * resolves the ids, not here.
 */
class DeleteEstimatesRequest extends FormRequest
{
    /**
     * The ability is checked in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => 'required',
            'ids.*' => ['required', Rule::exists('estimates', 'id')],
        ];
    }
}
