<?php

namespace App\Domains\Reporting\Http\Controllers;

use App\Domains\Contacts\Models\Customer;
use App\Domains\Reporting\Http\Requests\CustomerStatementRequest;
use App\Domains\Reporting\Queries\CustomerStatementQuery;
use App\Domains\Reporting\Rendering\CustomerStatementPdfRenderer;
use App\Platform\Http\Controller;
use Carbon\Carbon;
use Silber\Bouncer\BouncerFacade;

class CustomerStatementReportController extends Controller
{
    public function __construct(
        private readonly CustomerStatementQuery $customerStatementQuery,
        private readonly CustomerStatementPdfRenderer $customerStatementPdfRenderer,
    ) {}

    public function __invoke(CustomerStatementRequest $request, Customer $customer)
    {
        BouncerFacade::scope()->to($customer->company_id);

        $this->authorize('view', $customer);
        $this->authorize('view report', $customer->company);

        $type = $request->validated('type');

        $statement = $this->customerStatementQuery->statement(
            $customer,
            $type,
            Carbon::createFromFormat('Y-m-d', $request->validated('from_date')),
            Carbon::createFromFormat('Y-m-d', $request->validated($type === CustomerStatementQuery::TYPE_OUTSTANDING ? 'as_of' : 'to_date')),
            PHP_INT_MAX,
        );
        $pdf = $this->customerStatementPdfRenderer->render($statement);

        if ($request->boolean('preview')) {
            return view('app.pdf.reports.customer-statement', [
                'statement' => $statement,
                'customer' => $customer,
                'company' => $customer->company,
                'currency' => $statement['currency'],
                'logo' => $customer->company->logo_path,
            ]);
        }

        if ($request->boolean('download')) {
            return $pdf->download(__('Customer Statement').' '.$customer->name.'.pdf');
        }

        return $pdf->stream();
    }
}
