<?php

namespace App\Domains\Contacts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Guards the bulk-removal endpoint.
 *
 * Every id has to name a contact, but the existence check is not company
 * scoped: an id owned by another company clears validation here and is then
 * filtered out by the controller's company-scoped lookup, so the batch skips
 * it without complaint. Kept as it stands.
 */
class DeleteCustomersRequest extends FormRequest
{
    /**
     * Permission is settled elsewhere: the controller asks Bouncer for the
     * `delete multiple customers` ability before it touches anything.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A non-empty `ids` list, each entry naming a row that exists.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required'],
            'ids.*' => ['required', Rule::exists('customers', 'id')],
        ];
    }
}
