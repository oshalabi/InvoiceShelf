<?php

namespace App\Platform\Mail\Policies;

use App\Domains\Accounts\Models\User;

class MailAccessPolicy
{
    public function manageConfiguration(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
