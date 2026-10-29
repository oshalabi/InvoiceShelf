<?php

namespace App\Domains\Metadata\Http\Controllers;

use App\Domains\Metadata\Application\CustomFieldService;
use App\Domains\Metadata\Http\Requests\CustomFieldRequest;
use App\Domains\Metadata\Http\Resources\CustomFieldResource;
use App\Domains\Metadata\Models\CustomField;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;

/**
 * The definitions behind the extra questions a company asks on its records.
 *
 * A definition carries its own default answer, which arrives beside the
 * validated attributes rather than among them: the form request never lists
 * `default_answer`, because the column it belongs in follows from the field's
 * input type and is settled by the service.
 */
class CustomFieldsController extends Controller
{
    public function __construct(
        private readonly CustomFieldService $customFieldService,
    ) {}

    /**
     * A page of the company's definitions, newest first.
     *
     * The raw input goes to the filter scope untouched, so `type` (matched
     * against the model the field is attached to) and `search` are both read
     * there. A `limit` of "all" makes the pagination scope hand back the whole
     * collection instead of a paginator; with no `limit` a page holds five.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', CustomField::class);

        $perPage = $request->has('limit') ? $request->limit : 5;

        $definitions = CustomField::applyFilters($request->all())
            ->whereCompany()
            ->latest()
            ->paginateData($perPage);

        return CustomFieldResource::collection($definitions);
    }

    /**
     * Define a new field. The company comes from the request header rather
     * than the body, and the slug is minted once, here, from the model type
     * and the name.
     */
    public function store(CustomFieldRequest $request)
    {
        $this->authorize('create', CustomField::class);

        $companyId = (int) $request->header('company');

        $customField = $this->customFieldService->create(
            $request->validated(),
            $request->input('default_answer'),
            $companyId,
        );

        return new CustomFieldResource($customField);
    }

    public function show(CustomField $customField)
    {
        $this->authorize('view', $customField);

        return new CustomFieldResource($customField);
    }

    /**
     * Rewrite a definition in place. The slug is not among the attributes the
     * service touches, so a renamed field answers to the name it was born
     * with — which is what keeps existing formatting placeholders working.
     */
    public function update(CustomFieldRequest $request, CustomField $customField)
    {
        $this->authorize('update', $customField);

        $this->customFieldService->update(
            $customField,
            $request->validated(),
            $request->input('default_answer'),
        );

        return new CustomFieldResource($customField);
    }

    /**
     * Delete a definition together with every answer ever recorded against it.
     *
     * The payload publishes `in_use`, but this endpoint never consults it:
     * stored answers are swept first, the definition goes second, and there is
     * no guard and nothing to confirm. Warning the operator is left to the
     * interface. The probe in front of the sweep is redundant — an
     * unconditional delete would remove the same rows — and is kept so the
     * query trace stays what callers have always seen.
     */
    public function destroy(CustomField $customField)
    {
        $this->authorize('delete', $customField);

        $answers = $customField->customFieldValues();

        if ($answers->exists()) {
            $answers->delete();
        }

        $customField->forceDelete();

        return response()->json([
            'success' => true,
        ]);
    }
}
