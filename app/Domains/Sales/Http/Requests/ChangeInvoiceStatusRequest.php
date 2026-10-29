<?php

namespace App\Domains\Sales\Http\Requests;

use App\Domains\Sales\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeInvoiceStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    Invoice::STATUS_SENT,
                    Invoice::STATUS_COMPLETED,
                ]),
            ],
        ];
    }
}
