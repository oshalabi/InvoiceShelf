<?php

namespace App\Platform\Http;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * The class every controller in the application is built on.
 *
 * It adds no behaviour of its own. What it does is settle, in one place, the
 * three helper sets a controller may assume are there: authorisation
 * ($this->authorize(), authorizeForUser(), authorizeResource()), job dispatch
 * ($this->dispatch(), dispatchSync()) and inline validation ($this->validate()).
 * Controllers across the whole codebase call into all three, so the list below
 * is a contract with them: dropping a trait breaks call sites far from here,
 * and the framework's own base class deliberately ships without them.
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;
}
