<?php

namespace App\Domains\Catalog\Http\Controllers;

use App\Domains\Catalog\Http\Requests\UnitRequest;
use App\Domains\Catalog\Http\Resources\UnitResource;
use App\Domains\Catalog\Models\Unit;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;

/**
 * Endpoints for the measurement units an item can be sold in.
 *
 * Quirk kept as is: the unit policy defers every one of these actions to the
 * item view ability, so whoever may look at the catalog may also add, rename
 * and retire its units -- while someone allowed to edit items but not to view
 * them is turned away from all of them.
 */
class UnitsController extends Controller
{
    /**
     * Rows per page when the caller does not ask for a size of its own.
     */
    private const DEFAULT_LIMIT = 5;

    /**
     * Machine-readable key reported when a unit is still in use.
     */
    private const IN_USE_ERROR = 'items_attached';

    /**
     * Human-readable counterpart of the in-use key.
     */
    private const IN_USE_MESSAGE = 'Items Attached';

    /**
     * One page of the company's units, newest first.
     *
     * Clause order is deliberate: the query-string filters go on before the
     * company narrowing. A `limit` of "all" returns the whole set instead.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Unit::class);

        $filters = $request->all();

        $results = Unit::query()
            ->applyFilters($filters)
            ->whereCompany()
            ->latest()
            ->paginateData($request->input('limit', self::DEFAULT_LIMIT));

        return UnitResource::collection($results);
    }

    /**
     * A single unit.
     */
    public function show(Unit $unit)
    {
        $this->authorize('view', $unit);

        return new UnitResource($unit);
    }

    /**
     * Register a unit under the acting company.
     *
     * The resource answers 201 by itself, because the wrapped model was just
     * created and the verb is POST.
     */
    public function store(UnitRequest $request)
    {
        $this->authorize('create', Unit::class);

        $payload = $request->getUnitPayload();

        return new UnitResource(Unit::create($payload));
    }

    /**
     * Rename an existing unit.
     */
    public function update(UnitRequest $request, Unit $unit)
    {
        $this->authorize('update', $unit);

        $payload = $request->getUnitPayload();

        $unit->update($payload);

        return new UnitResource($unit);
    }

    /**
     * Retire a unit, provided no catalog item still measures by it.
     *
     * A unit in use is refused with the validation-shaped rejection above.
     * A successful removal reports its outcome as a sentence under `success`
     * rather than as a flag -- kept as is, clients read the text.
     */
    public function destroy(Unit $unit)
    {
        $this->authorize('delete', $unit);

        if ($unit->items()->exists()) {
            return respondJson(self::IN_USE_ERROR, self::IN_USE_MESSAGE);
        }

        $unit->delete();

        return response()->json(['success' => 'Unit deleted successfully']);
    }
}
