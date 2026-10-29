<?php

namespace App\Platform\Operations\Installation\Http\Controllers;

use App\Platform\Http\Controller;
use App\Platform\Operations\Installation\Application\EnvironmentManager;
use App\Platform\Operations\Installation\Http\Requests\DomainEnvironmentRequest;
use Illuminate\Support\Facades\Artisan;

/**
 * The wizard's domain step: records the host this instance will be served
 * from, so sessions and stateful API calls are scoped to it.
 */
class AppDomainController extends Controller
{
    /**
     * Compiled configuration is dropped first, otherwise the manager would
     * compare the submitted domain against a cached application URL.
     *
     * The step answers success unconditionally — a failed write has never been
     * reported to the client here, and the wizard's later steps rewrite the
     * same two variables anyway. Surfacing an error at this point would change
     * the contract the wizard front end is built against.
     */
    public function __invoke(DomainEnvironmentRequest $request)
    {
        Artisan::call('optimize:clear');

        (new EnvironmentManager)->saveDomainVariables($request);

        return response()->json([
            'success' => true,
        ]);
    }
}
