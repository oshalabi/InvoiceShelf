<?php

namespace App\Domains\Accounts\Contracts;

use App\Domains\Accounts\Models\User;

interface MemberReferencesCleaner
{
    public function clear(User $user): void;
}
