<?php

namespace App\Platform\Pdf\Policies;

use App\Domains\Accounts\Models\User;

class PdfAccessPolicy
{
    public function manageConfiguration(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
