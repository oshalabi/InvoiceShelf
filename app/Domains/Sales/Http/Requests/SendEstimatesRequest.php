<?php

namespace App\Domains\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The envelope of an estimate mail: who it goes to, what it says, and the
 * optional carbon copies.
 */
class SendEstimatesRequest extends FormRequest
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
            'subject' => 'required',
            'body' => 'required',
            'from' => 'required',
            'to' => 'required',
            'cc' => 'nullable',
            'bcc' => 'nullable',
        ];
    }
}
