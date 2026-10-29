<?php

namespace App\Domains\Contacts\Http\Controllers\CustomerPortal;

use App\Domains\Contacts\Contracts\CustomerPortalDashboardProvider;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(
        private readonly CustomerPortalDashboardProvider $customerPortalDashboardProvider,
    ) {}

    /**
     * Answer with the portal dashboard figures for the signed-in contact.
     *
     * @return Response
     */
    public function __invoke(Request $request)
    {
        return response()->json(
            $this->customerPortalDashboardProvider->get(Auth::guard('customer')->user())
        );
    }
}
