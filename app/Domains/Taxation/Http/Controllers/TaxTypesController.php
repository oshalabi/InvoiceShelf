<?php

namespace App\Domains\Taxation\Http\Controllers;

use App\Domains\Taxation\Http\Requests\TaxTypeRequest;
use App\Domains\Taxation\Http\Resources\TaxTypeResource;
use App\Domains\Taxation\Models\TaxType;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;

/**
 * Manages the tax types a company maintains by hand.
 *
 * Only the GENERAL kind is reachable from here; types owned by an installed
 * module stay out of every listing and are never created or removed through
 * these endpoints.
 */
class TaxTypesController extends Controller
{
    /**
     * Page of the company's own tax types, newest first.
     *
     * The raw request input is handed to the filter scope untouched, so
     * `search`, `transaction_type`, `tax_type_id` and the order pair are all
     * read there. A `limit` of "all" makes the paginate scope return the whole
     * collection instead of a paginator.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', TaxType::class);

        $perPage = $request->has('limit') ? $request->limit : 5;

        // Clause order matters: the filters go on first, then the kind and
        // company narrowing, and `latest()` trails any explicit ordering.
        $taxTypes = TaxType::applyFilters($request->all())
            ->where('type', TaxType::TYPE_GENERAL)
            ->whereCompany()
            ->latest()
            ->paginateData($perPage);

        return TaxTypeResource::collection($taxTypes);
    }

    /**
     * Persist a new tax type. The kind and the company are pinned by the
     * request object, whatever the client sent for them.
     *
     * The resource answers 201 by itself, because the wrapped model was just
     * created and the verb is POST.
     */
    public function store(TaxTypeRequest $request)
    {
        $this->authorize('create', TaxType::class);

        $taxType = TaxType::create($request->getTaxTypePayload());

        return new TaxTypeResource($taxType);
    }

    public function show(TaxType $taxType)
    {
        $this->authorize('view', $taxType);

        return new TaxTypeResource($taxType);
    }

    public function update(TaxTypeRequest $request, TaxType $taxType)
    {
        $this->authorize('update', $taxType);

        $taxType->update($request->getTaxTypePayload());

        return new TaxTypeResource($taxType);
    }

    /**
     * Drop a tax type, unless documents already carry a tax taken from it.
     *
     * Applied taxes are snapshots that keep pointing at their origin, so the
     * row has to survive for as long as any of them exist; the refusal is a
     * 422 carrying the `taxes_attached` key the SPA switches on.
     */
    public function destroy(TaxType $taxType)
    {
        $this->authorize('delete', $taxType);

        if ($taxType->taxes()->exists()) {
            return respondJson('taxes_attached', 'Taxes Attached.');
        }

        $taxType->delete();

        return response()->json([
            'success' => true,
        ]);
    }
}
