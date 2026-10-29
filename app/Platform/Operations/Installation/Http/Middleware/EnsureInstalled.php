<?php

namespace App\Platform\Operations\Installation\Http\Middleware;

use App\Platform\Operations\Installation\Application\InstallationState;
use App\Platform\Operations\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the application proper out of reach until the setup wizard is done.
 *
 * The question "is this instance installed?" is answered against a database
 * that may not exist yet, so every failure mode -- no connection, no schema,
 * no settings row -- is read as "not installed" and sends the visitor to the
 * wizard rather than to a stack trace.
 */
class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        return $this->isInstalled()
            ? $next($request)
            : redirect('/installation');
    }

    /**
     * A finished install means the schema is in place and the wizard wrote its
     * completion marker. The marker is only looked up once the schema exists.
     */
    private function isInstalled(): bool
    {
        try {
            return InstallationState::isDbCreated()
                && Setting::getSetting('profile_complete') === 'COMPLETED';
        } catch (\Exception) {
            return false;
        }
    }
}
