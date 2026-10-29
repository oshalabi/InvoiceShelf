<?php

namespace App\Platform\Modules\Policies;

use App\Domains\Accounts\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ModulePolicy
{
    use HandlesAuthorization;

    public function manageModules(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function manageSettings(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isOwner();
    }
}
