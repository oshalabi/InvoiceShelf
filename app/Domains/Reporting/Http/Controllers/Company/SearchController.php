<?php

namespace App\Domains\Reporting\Http\Controllers\Company;

use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The header search box, and the lookup behind "invite an existing account".
 */
class SearchController extends Controller
{
    /**
     * Contacts of the active company, newest first, plus — for an owner only —
     * the members matching the same term.
     *
     * @return Response
     */
    public function __invoke(Request $request)
    {
        $term = $request->only(['search']);

        // The company narrowing is applied after the contact filters and
        // before the member ones. The two orders are not interchangeable: a
        // filter that contributes an `orWhere` at the top level widens
        // whatever sits to its left, so the sequence is kept as it stands.
        $customers = Customer::query()
            ->applyFilters($term)
            ->whereCompany()
            ->latest()
            ->paginate(10);

        $users = [];

        if ($request->user()->isOwner()) {
            $users = User::query()
                ->whereCompany()
                ->applyFilters($term)
                ->latest()
                ->paginate(10);
        }

        return response()->json([
            'customers' => $customers,
            'users' => $users,
        ]);
    }

    /**
     * Accounts whose email contains the given fragment.
     *
     * KNOWN DEFECT, reproduced deliberately: the lookup is not scoped to a
     * company. It backs the invite flow, which has to be able to find an
     * account that has no membership here yet, so it reads across the whole
     * installation and discloses the existence and name of accounts belonging
     * to other tenants. The only gate is the right to create a member.
     */
    public function users(Request $request)
    {
        $this->authorize('create', User::class);

        return response()->json([
            'users' => User::query()
                ->whereEmail($request->email)
                ->latest()
                ->paginate(10),
        ]);
    }
}
