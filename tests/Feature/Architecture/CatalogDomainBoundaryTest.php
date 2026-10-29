<?php

use App\Adapters\Catalog\TaxationItemTaxManager;
use App\Domains\Catalog\CatalogServiceProvider;
use App\Domains\Catalog\Contracts\ItemTaxManager;
use App\Domains\Catalog\Models\Item;
use App\Domains\Catalog\Models\Unit;
use App\Domains\Catalog\Policies\ItemPolicy;
use App\Domains\Catalog\Policies\UnitPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

test('the catalog domain owns item behavior and authorization', function () {
    expect(app()->getProviders(CatalogServiceProvider::class))->toHaveCount(1)
        ->and(app(ItemTaxManager::class))->toBeInstanceOf(TaxationItemTaxManager::class)
        ->and(Gate::getPolicyFor(Item::class))->toBeInstanceOf(ItemPolicy::class)
        ->and(Gate::getPolicyFor(Unit::class))->toBeInstanceOf(UnitPolicy::class)
        ->and(Gate::has('delete multiple items'))->toBeTrue();

    expect(class_exists('App\\Services\\ItemService'))->toBeFalse()
        ->and(class_exists('App\\Policies\\ItemPolicy'))->toBeFalse()
        ->and(class_exists('App\\Policies\\UnitPolicy'))->toBeFalse()
        ->and(class_exists('App\\Http\\Controllers\\Company\\Item\\ItemsController'))->toBeFalse()
        ->and(class_exists('App\\Http\\Controllers\\Company\\Item\\UnitsController'))->toBeFalse()
        ->and(class_exists('App\\Http\\Requests\\ItemsRequest'))->toBeFalse()
        ->and(class_exists('App\\Http\\Requests\\DeleteItemsRequest'))->toBeFalse()
        ->and(class_exists('App\\Http\\Requests\\UnitRequest'))->toBeFalse()
        ->and(class_exists('App\\Http\\Resources\\ItemResource'))->toBeFalse()
        ->and(class_exists('App\\Http\\Resources\\UnitResource'))->toBeFalse()
        ->and(class_exists('App\\Http\\Resources\\ItemCollection'))->toBeFalse()
        ->and(class_exists('App\\Http\\Resources\\UnitCollection'))->toBeFalse();
});

test('the catalog domain preserves item and unit routes and middleware', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => preg_match('#^api/v1/(?:items|units)(?:$|/)#', $route->uri()) === 1)
        ->keyBy(fn ($route): string => implode('|', $route->methods()).' '.$route->uri());

    expect($routes->keys()->sort()->values()->all())->toBe(collect([
        'DELETE api/v1/items/{item}',
        'DELETE api/v1/units/{unit}',
        'GET|HEAD api/v1/items',
        'GET|HEAD api/v1/items/create',
        'GET|HEAD api/v1/items/{item}',
        'GET|HEAD api/v1/items/{item}/edit',
        'GET|HEAD api/v1/units',
        'GET|HEAD api/v1/units/create',
        'GET|HEAD api/v1/units/{unit}',
        'GET|HEAD api/v1/units/{unit}/edit',
        'POST api/v1/items',
        'POST api/v1/items/delete',
        'POST api/v1/units',
        'PUT|PATCH api/v1/items/{item}',
        'PUT|PATCH api/v1/units/{unit}',
    ])->sort()->values()->all());

    foreach ($routes as $route) {
        expect($route->getActionName())
            ->toStartWith('App\\Domains\\Catalog\\Http\\Controllers\\')
            ->and($route->gatherMiddleware())->toContain('auth:sanctum', 'company', 'bouncer');
    }
});
