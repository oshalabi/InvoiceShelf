<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as FrameworkRouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends FrameworkRouteServiceProvider
{
    /**
     * Where a signed-in staff user lands.
     *
     * The authentication layer redirects here once credentials check out.
     *
     * @var string
     */
    public const HOME = '/admin/dashboard';

    /**
     * Where a signed-in portal customer lands.
     *
     * The customer guard redirects here once credentials check out.
     *
     * @var string
     */
    public const CUSTOMER_HOME = '/customer/dashboard';

    /**
     * Install the throttling rules, then mount the route files.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Declare the named rate limiters this application offers.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60));
    }
}
