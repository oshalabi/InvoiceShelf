<?php

namespace App\Platform\Operations\Installation\Http\Middleware;

use App\Platform\Operations\Installation\Application\InstallationState;
use App\Platform\Operations\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The mirror image of the installed gate, guarding the wizard itself: once the
 * instance is live nobody gets to walk through setup a second time.
 */
class RedirectIfInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->wizardAlreadyFinished()) {
            return redirect('login');
        }

        return $next($request);
    }

    /**
     * Reading the completion marker needs the settings table, which the wizard
     * itself creates -- so a failure here just means setup is still running and
     * the request is allowed through.
     */
    private function wizardAlreadyFinished(): bool
    {
        if (! InstallationState::isDbCreated()) {
            return false;
        }

        try {
            return Setting::getSetting('profile_complete') === 'COMPLETED';
        } catch (\Exception) {
            return false;
        }
    }
}
