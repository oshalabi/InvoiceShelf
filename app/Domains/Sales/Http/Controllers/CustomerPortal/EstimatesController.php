<?php

namespace App\Domains\Sales\Http\Controllers\CustomerPortal;

use App\Domains\Accounts\Models\Company;
use App\Domains\Sales\Http\Resources\CustomerPortal\EstimateResource;
use App\Domains\Sales\Models\Estimate;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class EstimatesController extends Controller
{
    /**
     * Page through the offers addressed to the signed-in contact.
     *
     * Unsent work stays private to the issuer, so drafts reach neither the
     * page itself nor the counter beside it.
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

        $query = Estimate::with(['items', 'customer', 'taxes', 'creator'])
            ->where('status', '<>', Estimate::STATUS_DRAFT)
            ->whereCustomer($contact);

        $query->applyFilters($request->only([
            'status',
            'estimate_number',
            'from_date',
            'to_date',
            'orderByField',
            'orderBy',
        ]));

        $page = $query->latest()->paginateData($perPage);

        $visible = Estimate::query()
            ->where('status', '<>', Estimate::STATUS_DRAFT)
            ->whereCustomer($contact)
            ->count();

        return EstimateResource::collection($page)
            ->additional(['meta' => [
                'estimateTotalCount' => $visible,
            ]]);
    }

    /**
     * Hand back a single offer, looked up inside the portal's company and
     * narrowed to the signed-in contact so ids cannot be probed.
     *
     * @param  string  $id
     * @return Response
     */
    public function show(Company $company, $id)
    {
        $contact = Auth::guard('customer')->id();

        $estimate = $company->estimates()->whereCustomer($contact)->where('id', $id)->first();

        if ($estimate === null) {
            return response()->json(['error' => 'estimate_not_found'], Response::HTTP_NOT_FOUND);
        }

        return EstimateResource::make($estimate);
    }
}
