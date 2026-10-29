<?php

use App\Domains\Taxation\Models\TaxType;
use App\Domains\Taxation\Policies\TaxTypePolicy;
use App\Domains\Taxation\TaxationServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

test('the taxation domain owns tax-type authorization and HTTP adapters', function () {
    expect(app()->getProviders(TaxationServiceProvider::class))->toHaveCount(1)
        ->and(Gate::getPolicyFor(TaxType::class))->toBeInstanceOf(TaxTypePolicy::class);

    expect(class_exists('App\\Policies\\TaxTypePolicy'))->toBeFalse()
        ->and(class_exists('App\\Http\\Controllers\\Company\\Settings\\TaxTypesController'))->toBeFalse()
        ->and(class_exists('App\\Http\\Requests\\TaxTypeRequest'))->toBeFalse()
        ->and(class_exists('App\\Http\\Resources\\TaxTypeResource'))->toBeFalse()
        ->and(class_exists('App\\Http\\Resources\\TaxResource'))->toBeFalse()
        ->and(class_exists('App\\Http\\Resources\\Customer\\TaxTypeResource'))->toBeFalse()
        ->and(class_exists('App\\Http\\Resources\\Customer\\TaxResource'))->toBeFalse();
});

test('the taxation domain preserves tax-type routes and middleware', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/tax-types'))
        ->keyBy(fn ($route): string => implode('|', $route->methods()).' '.$route->uri());

    expect($routes->keys()->sort()->values()->all())->toBe(collect([
        'DELETE api/v1/tax-types/{tax_type}',
        'GET|HEAD api/v1/tax-types',
        'GET|HEAD api/v1/tax-types/{tax_type}',
        'POST api/v1/tax-types',
        'PUT|PATCH api/v1/tax-types/{tax_type}',
    ])->sort()->values()->all());

    foreach ($routes as $route) {
        expect($route->getActionName())
            ->toStartWith('App\\Domains\\Taxation\\Http\\Controllers\\')
            ->and($route->gatherMiddleware())->toContain('auth:sanctum', 'company', 'bouncer');
    }
});
