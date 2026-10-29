<?php

namespace App\Domains\Sales\Http\Controllers\CustomerPortal;

use App\Domains\Accounts\Models\Company;
use App\Domains\Sales\Http\Resources\CustomerPortal\InvoiceResource;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class InvoicesController extends Controller
{
    /**
     * Page through the billing documents the signed-in contact has received.
     *
     * Drafts are withheld; the whole query string is handed to the filter
     * scope, so anything the model knows how to narrow by is accepted here.
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
        $filters = $request->all();

        $page = Invoice::with(['items', 'customer', 'creator', 'taxes'])
            ->where('status', '<>', Invoice::STATUS_DRAFT)
            ->applyFilters($filters)
            ->whereCustomer($contact)
            ->latest()
            ->paginateData($perPage);

        // The counter tallies issued documents alone. A credit note reverses
        // an invoice rather than adding one, so it is left out of the total
        // even though it is listed among the rows above.
        $received = Invoice::query()
            ->where('type', Invoice::TYPE_INVOICE)
            ->where('status', '<>', Invoice::STATUS_DRAFT)
            ->whereCustomer($contact)
            ->count();

        return InvoiceResource::collection($page)
            ->additional(['meta' => [
                'invoiceTotalCount' => $received,
            ]]);
    }

    /**
     * Hand back a single billing document, looked up inside the portal's
     * company and narrowed to the signed-in contact.
     *
     * @param  string  $id
     * @return Response
     */
    public function show(Company $company, $id)
    {
        $contact = Auth::guard('customer')->id();

        $invoice = $company->invoices()->whereCustomer($contact)->where('id', $id)->first();

        if ($invoice === null) {
            return response()->json(['error' => 'invoice_not_found'], Response::HTTP_NOT_FOUND);
        }

        return InvoiceResource::make($invoice);
    }
}
