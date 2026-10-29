<?php

namespace App\Domains\Accounts\Policies;

use App\Domains\Accounts\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Who may administer staff accounts.
 *
 * Ownership here is positional and evaluated per request: the actor counts as
 * an owner only while the company named in the `company` header is the company
 * they own, so the same person is an owner on one call and nobody on the next.
 *
 * Decisions aimed at an existing account carry a second half — the account has
 * to sit inside that same header company — which keeps the owner of one tenant
 * from reading or overwriting an account that lives in another. Only view,
 * update and delete carry that half; the remaining entries stop after the
 * ownership question, which is noted where it happens.
 */
class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Browsing the member list.
     */
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    /**
     * Reading one member.
     */
    public function view(User $user, User $model): bool
    {
        return $this->mayActOn($user, $model);
    }

    /**
     * Adding a member.
     *
     * No target exists yet, so ownership of the header company is the whole
     * decision.
     */
    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    /**
     * Editing one member.
     */
    public function update(User $user, User $model): bool
    {
        return $this->mayActOn($user, $model);
    }

    /**
     * Removing one member.
     *
     * Nothing routes here today — member removal arrives through the bulk gate
     * below — but the tenant half is applied all the same.
     */
    public function delete(User $user, User $model): bool
    {
        return $this->mayActOn($user, $model);
    }

    /**
     * Bringing back a removed member.
     *
     * Unreachable: accounts are erased outright rather than soft-deleted. Note
     * that the target is ignored, so ownership alone would answer this.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->isOwner();
    }

    /**
     * Erasing a member for good.
     *
     * Unreachable for the same reason, and likewise blind to the target.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->isOwner();
    }

    /**
     * Inviting a member.
     *
     * Nothing calls this. The declaration is kept as found, return type
     * included — that is, without one — and the target goes unexamined.
     */
    public function invite(User $user, User $model)
    {
        return $user->isOwner();
    }

    /**
     * Removing members in bulk.
     *
     * Reached as a gate rather than through a model, so there is no target to
     * confine: ownership of the header company opens the whole operation, and
     * the ids it is handed are resolved installation-wide.
     */
    public function deleteMultiple(User $user)
    {
        return $user->isOwner();
    }

    /**
     * Both halves: own the header company, and have the target inside it.
     */
    private function mayActOn(User $user, User $target): bool
    {
        return $user->isOwner() && $this->isMemberOfActiveCompany($target);
    }

    /**
     * Membership of the company carried by the request header.
     *
     * Without this the target would be looked up by installation-wide id, and
     * one company's owner could reach another company's people.
     */
    private function isMemberOfActiveCompany(User $target): bool
    {
        $activeCompanyId = request()->header('company');

        if (! $activeCompanyId) {
            return false;
        }

        return $target->companies()->whereKey($activeCompanyId)->exists();
    }
}
