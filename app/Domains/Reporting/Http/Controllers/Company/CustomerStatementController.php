<?php

namespace App\Domains\Reporting\Http\Controllers\Company;

use App\Domains\Contacts\Models\Customer;
use App\Domains\Reporting\Http\Requests\CustomerStatementRequest;
use App\Domains\Reporting\Http\Resources\CustomerStatementResource;
use App\Domains\Reporting\Queries\CustomerStatementQuery;
use App\Platform\Http\Controller;
use Carbon\Carbon;

class CustomerStatementController extends Controller
{
    public function __construct(
        private readonly CustomerStatementQuery $customerStatementQuery,
    ) {}

    public function __invoke(CustomerStatementRequest $request, Customer $customer): CustomerStatementResource
    {
        $this->authorize('view', $customer);
        $this->authorize('view report', $customer->company);

        $type = $request->validated('type');

        $statement = $this->customerStatementQuery->statement(
            $customer,
            $type,
            Carbon::createFromFormat('Y-m-d', $request->validated('from_date')),
            Carbon::createFromFormat('Y-m-d', $request->validated($type === CustomerStatementQuery::TYPE_OUTSTANDING ? 'as_of' : 'to_date')),
            (int) $request->validated('per_page', 50),
            (int) $request->validated('page', 1),
        );

        return new CustomerStatementResource($statement);
    }
}
