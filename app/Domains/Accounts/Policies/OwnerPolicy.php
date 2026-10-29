<?php

namespace App\Domains\Accounts\Policies;

use App\Domains\Accounts\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * The bare owner check, reached as a gate that takes no subject.
 *
 * Callers that guard something belonging to the active company — but have no
 * row to hand over — ask this one. It resolves to ownership of the company in
 * the `company` header and nothing else: no ability is consulted, and the
 * platform administrator is not waved through.
 */
class OwnerPolicy
{
    use HandlesAuthorization;

    /**
     * Declared without a return type, as found.
     */
    public function managedByOwner(User $user)
    {
        return $user->isOwner();
    }
}
