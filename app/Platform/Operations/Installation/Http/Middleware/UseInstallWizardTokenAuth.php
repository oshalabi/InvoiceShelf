<?php

namespace App\Platform\Operations\Installation\Http\Middleware;

use App\Platform\Operations\Installation\Application\InstallationState;
use App\Platform\Operations\Installation\Authentication\InstallWizardAuth;
use App\Platform\Operations\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseInstallWizardTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! InstallWizardAuth::isRequest($request) || ! $this->installationIsIncomplete()) {
            return $next($request);
        }

        config([
            'sanctum.guard' => [],
            'sanctum.stateful' => [],
        ]);
        $request->attributes->set('install_wizard', true);

        return $next($request);
    }

    private function installationIsIncomplete(): bool
    {
        if (! InstallationState::isDbCreated()) {
            return true;
        }

        try {
            return Setting::getSetting('profile_complete') !== 'COMPLETED';
        } catch (\Exception $e) {
            return true;
        }
    }
}
