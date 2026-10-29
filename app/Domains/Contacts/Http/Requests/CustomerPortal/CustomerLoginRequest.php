<?php

namespace App\Domains\Contacts\Http\Requests\CustomerPortal;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shape check for the portal login form.
 *
 * Deliberately loose: the address only has to be text, not to look like an
 * email, so a contact whose stored address is unusual can still sign in.
 * Whether the pair is correct is settled by the controller, not here.
 */
class CustomerLoginRequest extends FormRequest
{
    /**
     * The login form is open to anyone standing in front of the portal.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Both fields are mandatory, and both must be textual.
     */
    public function rules(): array
    {
        return [
            'email' => 'required|string',
            'password' => 'required|string',
        ];
    }
}
