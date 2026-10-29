<?php

namespace App\Domains\Contacts\Http\Controllers\Company;

use App\Domains\Contacts\Application\CustomerService;
use App\Domains\Contacts\Http\Requests\CustomerRequest;
use App\Domains\Contacts\Http\Requests\DeleteCustomersRequest;
use App\Domains\Contacts\Http\Resources\CustomerResource;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Reporting\Queries\CustomerStatementQuery;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Admin-side CRUD over the contacts of the active company.
 *
 * Nothing is decided here: the form request shapes the payload, the service
 * owns every write, and the statement query decorates whatever is about to be
 * serialised with the receivable figures the SPA prints beside each contact.
 */
class CustomersController extends Controller
{
    public function __construct(
        private readonly CustomerService $customerService,
        private readonly CustomerStatementQuery $customerStatementQuery,
    ) {}

    /**
     * A page of contacts, plus the company-wide head count in the envelope.
     *
     * Clause order below is load-bearing. The tenancy clause has to reach the
     * builder before the filters do, because the `customer_id` filter joins
     * itself on with OR — behind the filters it would be swallowed by that OR
     * and the page would leak rows from other companies. Kept as it stands.
     *
     * Page size defaults to ten; the literal `limit=all` makes the paginate
     * scope return a plain collection rather than a paginator.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Customer::class);

        $perPage = $request->has('limit') ? $request->limit : 10;
        $filters = $request->all();

        $customers = Customer::query()
            ->with('creator')
            ->whereCompany()
            ->applyFilters($filters)
            ->paginateData($perPage);

        $this->withAccountSummaries($customers);

        $companyTotal = Customer::whereCompany()->count();

        return CustomerResource::collection($customers)->additional([
            'meta' => ['customer_total_count' => $companyTotal],
        ]);
    }

    /**
     * File a new contact.
     *
     * Address blocks and custom-field values ride along inside the service's
     * transaction; the request object decides whether either was supplied.
     */
    public function store(CustomerRequest $request)
    {
        $this->authorize('create', Customer::class);

        $created = $this->customerService->create(
            $request->customerAttributes(),
            $request->shippingAddress(),
            $request->billingAddress(),
            $request->customFields(),
        );

        $this->withAccountSummaries([$created]);

        return new CustomerResource($created);
    }

    public function show(Customer $customer)
    {
        $this->authorize('view', $customer);

        $this->withAccountSummaries([$customer]);

        return new CustomerResource($customer);
    }

    /**
     * Overwrite a contact.
     *
     * Two rules live in the service rather than the request: a currency change
     * is refused once any document exists, and the address rows are replaced
     * wholesale — so an update carrying no address block at all leaves the
     * contact with none.
     */
    public function update(CustomerRequest $request, Customer $customer)
    {
        $this->authorize('update', $customer);

        $saved = $this->customerService->update(
            $customer,
            $request->customerAttributes(),
            $request->shippingAddress(),
            $request->billingAddress(),
            $request->customFields(),
        );

        $this->withAccountSummaries([$saved]);

        return new CustomerResource($saved);
    }

    /**
     * Erase a batch of contacts together with everything filed against them —
     * estimates, invoices, payments, expenses, recurring invoices, addresses —
     * in a single transaction.
     *
     * The submitted ids were checked against the customers table globally, but
     * are narrowed to the active company here, so an id belonging to somebody
     * else clears validation and is then quietly dropped from the batch.
     */
    public function delete(DeleteCustomersRequest $request)
    {
        $this->authorize('delete multiple customers');

        $targets = Customer::whereCompany()->whereIn('id', $request->ids)->pluck('id');

        $this->customerService->delete($targets);

        return response()->json(['success' => true]);
    }

    /**
     * Hang the account summaries on the models that are about to be rendered.
     *
     * A paginator cannot be handed over as-is: cast to an array it yields the
     * envelope, not the rows. Everything else — a collection, or a one-element
     * array wrapping a single model — goes straight through.
     */
    private function withAccountSummaries(mixed $customers): void
    {
        if ($customers instanceof LengthAwarePaginator) {
            $customers = $customers->getCollection();
        }

        $this->customerStatementQuery->hydrateAccountSummaries($customers);
    }
}
