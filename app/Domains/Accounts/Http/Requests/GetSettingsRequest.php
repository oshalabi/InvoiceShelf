<?php

namespace App\Domains\Accounts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The query behind reading preferences: a list of option names to look up.
 *
 * The list itself has to be there, and each entry has to be a non-empty
 * string. Nothing checks that an option actually exists — unknown names are
 * simply absent from the reply.
 */
class GetSettingsRequest extends FormRequest
{
    /**
     * Reading preferences is open to every member of the company, so nothing
     * is refused here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'settings' => [
                'required',
            ],
            'settings.*' => [
                'required',
                'string',
            ],
        ];
    }
}
