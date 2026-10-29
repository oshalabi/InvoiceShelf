<?php

use App\Adapters\Contacts\EloquentCustomerDataPurger;
use App\Adapters\Contacts\EloquentCustomerPortalDashboardProvider;
use App\Adapters\Contacts\EloquentCustomerStatsProvider;
use App\Adapters\Contacts\MediaLibraryCustomerAvatarManager;
use App\Domains\Contacts\ContactsServiceProvider;
use App\Domains\Contacts\Contracts\CustomerAvatarManager;
use App\Domains\Contacts\Contracts\CustomerDataPurger;
use App\Domains\Contacts\Contracts\CustomerPortalDashboardProvider;
use App\Domains\Contacts\Contracts\CustomerStatsProvider;
use App\Domains\Contacts\Http\Middleware\CustomerGuest;
use App\Domains\Contacts\Http\Middleware\CustomerPortalMiddleware;
use App\Domains\Contacts\Http\Middleware\CustomerRedirectIfAuthenticated;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Contacts\Policies\CustomerPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

test('the contacts domain owns customer behavior and authorization', function () {
    expect(app()->getProviders(ContactsServiceProvider::class))->toHaveCount(1)
        ->and(app(CustomerAvatarManager::class))->toBeInstanceOf(MediaLibraryCustomerAvatarManager::class)
        ->and(app(CustomerDataPurger::class))->toBeInstanceOf(EloquentCustomerDataPurger::class)
        ->and(app(CustomerPortalDashboardProvider::class))->toBeInstanceOf(EloquentCustomerPortalDashboardProvider::class)
        ->and(app(CustomerStatsProvider::class))->toBeInstanceOf(EloquentCustomerStatsProvider::class)
        ->and(Gate::getPolicyFor(Customer::class))->toBeInstanceOf(CustomerPolicy::class)
        ->and(Gate::has('delete multiple customers'))->toBeTrue();

    foreach ([
        'App\\Services\\CustomerService',
        'App\\Policies\\CustomerPolicy',
        'App\\Notifications\\CustomerMailResetPasswordNotification',
        'App\\Http\\Controllers\\Admin\\CountriesController',
        'App\\Http\\Controllers\\Company\\Customer\\CustomersController',
        'App\\Http\\Controllers\\Company\\Customer\\CustomerStatsController',
        'App\\Http\\Controllers\\CustomerPortal\\Auth\\ForgotPasswordController',
        'App\\Http\\Controllers\\CustomerPortal\\Auth\\LoginController',
        'App\\Http\\Controllers\\CustomerPortal\\Auth\\ResetPasswordController',
        'App\\Http\\Controllers\\CustomerPortal\\General\\BootstrapController',
        'App\\Http\\Controllers\\CustomerPortal\\General\\DashboardController',
        'App\\Http\\Controllers\\CustomerPortal\\General\\ProfileController',
        'App\\Http\\Requests\\CustomerRequest',
        'App\\Http\\Requests\\DeleteCustomersRequest',
        'App\\Http\\Requests\\Customer\\CustomerLoginRequest',
        'App\\Http\\Requests\\Customer\\CustomerProfileRequest',
        'App\\Http\\Resources\\AddressResource',
        'App\\Http\\Resources\\CountryResource',
        'App\\Http\\Resources\\CustomerResource',
        'App\\Http\\Resources\\Customer\\AddressResource',
        'App\\Http\\Resources\\Customer\\CountryResource',
        'App\\Http\\Resources\\Customer\\CustomerResource',
        'App\\Http\\Resources\\AddressCollection',
        'App\\Http\\Resources\\CountryCollection',
        'App\\Http\\Resources\\CustomerCollection',
    ] as $legacyClass) {
        expect(class_exists($legacyClass))->toBeFalse();
    }
});

test('customer middleware aliases resolve to the contacts domain', function () {
    $middleware = app('router')->getMiddleware();

    expect($middleware['customer'])->toBe(CustomerRedirectIfAuthenticated::class)
        ->and($middleware['customer-guest'])->toBe(CustomerGuest::class)
        ->and($middleware['customer-portal'])->toBe(CustomerPortalMiddleware::class);
});

test('the contacts domain preserves public and company customer routes', function () {
    $routes = collect(Route::getRoutes()->getRoutes());

    $countryRoutes = $routes->filter(fn ($route): bool => in_array($route->uri(), [
        'api/v1/countries',
        'api/v1/{company}/customer/countries',
    ], true));

    expect($countryRoutes)->toHaveCount(2);

    foreach ($countryRoutes as $route) {
        expect($route->getActionName())->toBe('App\\Domains\\Contacts\\Http\\Controllers\\CountriesController');
    }

    $companyRoutes = $routes
        ->filter(fn ($route): bool => preg_match('#^api/v1/customers(?:$|/)#', $route->uri()) === 1)
        ->reject(fn ($route): bool => str_contains($route->uri(), '/statement') || str_contains($route->uri(), '/credit-allocations'));

    expect($companyRoutes)->toHaveCount(9);

    foreach ($companyRoutes as $route) {
        expect($route->getActionName())
            ->toStartWith('App\\Domains\\Contacts\\Http\\Controllers\\Company\\')
            ->and($route->gatherMiddleware())->toContain('auth:sanctum', 'company', 'bouncer');
    }
});

test('the contacts domain preserves customer portal identity routes', function () {
    $routes = collect(Route::getRoutes()->getRoutes());
    $portalRoutes = $routes
        ->filter(fn ($route): bool => str_starts_with($route->getActionName(), 'App\\Domains\\Contacts\\Http\\Controllers\\CustomerPortal\\'));

    expect($portalRoutes)->toHaveCount(8);

    $authenticatedRoutes = $portalRoutes
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/{company}/customer/'))
        ->reject(fn ($route): bool => str_contains($route->uri(), '/auth/'));

    expect($authenticatedRoutes)->toHaveCount(4);

    foreach ($authenticatedRoutes as $route) {
        expect($route->gatherMiddleware())->toContain('auth:customer', 'customer-portal');
    }
});
