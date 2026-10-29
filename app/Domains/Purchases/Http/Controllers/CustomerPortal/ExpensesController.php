<?php

namespace App\Domains\Purchases\Http\Controllers\CustomerPortal;

use App\Domains\Accounts\Models\Company;
use App\Domains\Purchases\Http\Resources\CustomerPortal\ExpenseResource;
use App\Domains\Purchases\Models\Expense;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class ExpensesController extends Controller
{
    /**
     * Page through the spend recorded against the signed-in contact.
     *
     * Only five of the admin filters reach the model -- the heading, the two
     * ends of a date range and the two ordering knobs. Free-text search and
     * the contact filter are dropped before the query is built, the latter
     * because the contact is decided by the session rather than the query
     * string.
     *
     * Scoping is by contact alone: the company in the URL narrows nothing
     * here, so a contact recorded under more than one company would see all of
     * their spend from any of that company's portals.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $perPage = 10;

        if ($request->has('limit')) {
            $perPage = $request->limit;
        }

        $contact = Auth::guard('customer')->id();

        $narrowing = $request->only([
            'expense_category_id',
            'from_date',
            'to_date',
            'orderByField',
            'orderBy',
        ]);

        // The creator is eager loaded but never published by the portal
        // payload, while half of what that payload does publish is left for it
        // to fetch a row at a time.
        $page = Expense::with(['category', 'creator', 'fields'])
            ->whereUser($contact)
            ->applyFilters($narrowing)
            ->paginateData($perPage);

        // Counted afresh rather than taken off the page, so the tally covers
        // everything on file for the contact, filters and paging aside.
        //
        // Except that it does not: there is no such scope, so the name falls
        // through to a dynamic clause on a column named "customer" that no
        // version of this table has ever had. SQLite reads the unmatched
        // identifier as the string 'customer', compares it to the contact id
        // and matches nothing, so the tally is reported as zero; MySQL and
        // PostgreSQL reject the column outright and the listing fails. Left
        // exactly as it stands.
        $recorded = Expense::whereCustomer($contact)->count();

        return ExpenseResource::collection($page)->additional([
            'meta' => ['expenseTotalCount' => $recorded],
        ]);
    }

    /**
     * Hand back a single expense, looked up inside the portal's company and
     * narrowed to the signed-in contact.
     *
     * @param  string  $id
     * @return Response
     */
    public function show(Company $company, $id)
    {
        $contact = Auth::guard('customer')->id();

        $expense = $company->expenses()->whereUser($contact)->where('id', $id)->first();

        if ($expense === null) {
            return response()->json(['error' => 'expense_not_found'], Response::HTTP_NOT_FOUND);
        }

        return ExpenseResource::make($expense);
    }
}
