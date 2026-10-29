<?php

namespace App\Domains\Receivables\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The composed receipt mail: an envelope and a body, both written by the
 * sender. Copies are optional.
 */
class SendPaymentRequest extends FormRequest
{
    /**
     * The send ability is checked by the controller, against the payment.
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
            'subject' => ['required'],
            'body' => ['required'],
            'from' => ['required'],
            'to' => ['required'],
            'cc' => ['nullable'],
            'bcc' => ['nullable'],
        ];
    }
}
