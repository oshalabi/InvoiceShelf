<?php

namespace App\Domains\Receivables\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReplacePaymentAllocationsRequest extends FormRequest
{
    /**
     * Access is settled by the controller, against the payment whose rows
     * are being re-cut; this class only shapes input.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The complete row set for one payment; an empty list clears it.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'allocations' => ['present', 'array'],
            'allocations.*.invoice_id' => ['required', 'integer', 'distinct'],
            'allocations.*.amount' => ['required', 'integer', 'min:1'],
        ];
    }
}
