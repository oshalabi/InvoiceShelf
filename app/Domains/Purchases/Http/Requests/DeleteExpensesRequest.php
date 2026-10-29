<?php

namespace App\Domains\Purchases\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteExpensesRequest extends FormRequest
{
    /**
     * Gatekeeping happens in the controller, so let every caller through here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rules for the bulk expense removal payload.
     */
    public function rules(): array
    {
        return [
            'ids' => ['required'],
            'ids.*' => ['required', Rule::exists('expenses', 'id')],
        ];
    }
}
