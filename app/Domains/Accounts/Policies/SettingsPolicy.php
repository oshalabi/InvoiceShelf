<?php

namespace App\Domains\Accounts\Policies;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Who may rewrite a company's own record and its settings.
 *
 * The question is positional and asked of the company being edited: is this
 * actor the account recorded against it as owner? Holding an ability, or being
 * the platform administrator, buys nothing here. The comparison is loose, so a
 * numeric string in the owner column still matches the id it names.
 */
class SettingsPolicy
{
    use HandlesAuthorization;

    /**
     * Editing the company profile, its settings, or its mail configuration.
     *
     * Declared without a return type, as found.
     */
    public function manageCompany(User $user, Company $company)
    {
        return $user->id == $company->owner_id;
    }
}
