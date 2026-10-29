<?php

namespace App\Domains\Receivables\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreditAllocationRequest extends FormRequest
{
    /**
     * Access is settled by the controller, which weighs the customer and
     * every payment named in the payload; this class only shapes input.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Credit rows: which payment covers which invoice, and by how much.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.payment_id' => ['required', 'integer'],
            'allocations.*.invoice_id' => ['required', 'integer'],
            'allocations.*.amount' => ['required', 'integer', 'min:1'],
        ];
    }
}
