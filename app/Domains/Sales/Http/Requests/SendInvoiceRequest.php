<?php

namespace App\Domains\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The envelope of an invoice mail: the message itself, who it comes from, who
 * it goes to, and the optional carbon copies.
 */
class SendInvoiceRequest extends FormRequest
{
    /**
     * The send ability is checked in the controller.
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
            'body' => 'required',
            'subject' => 'required',
            'from' => 'required',
            'to' => 'required',
            'cc' => 'nullable',
            'bcc' => 'nullable',
        ];
    }
}
