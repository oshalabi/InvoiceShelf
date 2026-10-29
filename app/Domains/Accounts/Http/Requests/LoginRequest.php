<?php

namespace App\Domains\Accounts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shape check for the token sign-in payload.
 *
 * Three fields have to be present: the address being claimed, the password
 * offered for it, and a label for the device the token is being minted for.
 * Whether the pair is actually correct is settled by the controller, not here,
 * so nothing in this class can be used to probe for existing accounts.
 *
 * Quirk kept as is: presence is all that is asked for. The address is not
 * required to look like an email, nor even to be a string, so a payload that
 * sends the field as an array passes validation and only comes apart further
 * down when the value is lower-cased for lookup.
 */
class LoginRequest extends FormRequest
{
    /**
     * The sign-in door is open to anyone standing in front of it.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * All three fields are mandatory and otherwise unconstrained.
     */
    public function rules(): array
    {
        return [
            'username' => ['required'],
            'password' => ['required'],
            'device_name' => ['required'],
        ];
    }
}
