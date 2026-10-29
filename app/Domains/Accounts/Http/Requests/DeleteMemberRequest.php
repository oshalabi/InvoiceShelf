<?php

namespace App\Domains\Accounts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Guards the bulk removal of staff accounts.
 *
 * Every submitted id has to name an account, but the check reaches across the
 * whole installation rather than the active company: an id belonging to another
 * tenant clears validation here and is then dropped by the controller's
 * company-scoped lookup, so the batch skips it without complaint. Kept as it
 * stands.
 */
class DeleteMemberRequest extends FormRequest
{
    /**
     * Nothing is settled at this layer — the controller asks Bouncer for the
     * `delete multiple users` ability before anything is touched.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A non-empty `users` list, each entry naming a row that exists.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'users' => ['required'],
            'users.*' => ['required', Rule::exists('users', 'id')],
        ];
    }
}
