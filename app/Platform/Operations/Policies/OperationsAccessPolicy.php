<?php

namespace App\Platform\Operations\Policies;

use App\Domains\Accounts\Models\User;

class OperationsAccessPolicy
{
    public function manage(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
