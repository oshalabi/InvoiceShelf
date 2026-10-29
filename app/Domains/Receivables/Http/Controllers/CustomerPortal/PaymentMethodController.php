<?php

namespace App\Domains\Receivables\Http\Controllers\CustomerPortal;

use App\Domains\Accounts\Models\Company;
use App\Domains\Receivables\Http\Resources\CustomerPortal\PaymentMethodResource;
use App\Domains\Receivables\Models\PaymentMethod;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentMethodController extends Controller
{
    /**
     * List the payment methods on the portal company's books.
     *
     * Every row is offered, whatever kind it is: manual labels sit alongside
     * gateway-registered ones, and neither the active flag nor the
     * test-environment flag is consulted. That is wider than what a gateway
     * picker wants, and it is kept that way on purpose.
     *
     * @return Response
     */
    public function __invoke(Request $request, Company $company)
    {
        $methods = PaymentMethod::query()
            ->where('company_id', $company->id)
            ->get();

        return PaymentMethodResource::collection($methods);
    }
}
