<?php

namespace App\Platform\Storage\Policies;

use App\Domains\Accounts\Models\User;

class StorageAccessPolicy
{
    public function manage(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
