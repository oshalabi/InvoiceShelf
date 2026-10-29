<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks the in-app updater when running inside the official Docker image.
 *
 * Containers are upgraded with `docker compose pull`, not by copying release
 * files over the image filesystem (which is ephemeral and reset on recreate).
 * The CONTAINERIZED flag is injected into .env by docker/production/inject.sh.
 */
class EnsureNotContainerized
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(
            (bool) config('invoiceshelf.containerized'),
            403,
            'The in-app updater is disabled in containerized installs. Upgrade with `docker compose pull`.'
        );

        return $next($request);
    }
}
