<?php

use App\Adapters\Purchases\MediaLibraryExpenseReceiptManager;
use App\Adapters\Purchases\MoneyExpenseExchangeRateRecorder;
use App\Adapters\Purchases\TaxationExpenseTaxManager;
use App\Domains\Purchases\Contracts\ExpenseExchangeRateRecorder;
use App\Domains\Purchases\Contracts\ExpenseReceiptManager;
use App\Domains\Purchases\Contracts\ExpenseTaxManager;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Purchases\Models\ExpenseCategory;
use App\Domains\Purchases\Policies\ExpenseCategoryPolicy;
use App\Domains\Purchases\Policies\ExpensePolicy;
use App\Domains\Purchases\PurchasesServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

test('the purchases domain owns expense behavior and authorization', function () {
    expect(app()->getProviders(PurchasesServiceProvider::class))->toHaveCount(1)
        ->and(app(ExpenseTaxManager::class))->toBeInstanceOf(TaxationExpenseTaxManager::class)
        ->and(app(ExpenseExchangeRateRecorder::class))->toBeInstanceOf(MoneyExpenseExchangeRateRecorder::class)
        ->and(app(ExpenseReceiptManager::class))->toBeInstanceOf(MediaLibraryExpenseReceiptManager::class)
        ->and(Gate::getPolicyFor(Expense::class))->toBeInstanceOf(ExpensePolicy::class)
        ->and(Gate::getPolicyFor(ExpenseCategory::class))->toBeInstanceOf(ExpenseCategoryPolicy::class)
        ->and(Gate::has('delete multiple expenses'))->toBeTrue();

    expect(class_exists('App\\Services\\Document\\ExpenseService'))->toBeFalse()
        ->and(class_exists('App\\Policies\\ExpensePolicy'))->toBeFalse()
        ->and(class_exists('App\\Policies\\ExpenseCategoryPolicy'))->toBeFalse()
        ->and(class_exists('App\\Http\\Controllers\\Company\\Expense\\ExpensesController'))->toBeFalse()
        ->and(class_exists('App\\Http\\Controllers\\Company\\Expense\\ExpenseCategoriesController'))->toBeFalse()
        ->and(class_exists('App\\Http\\Controllers\\CustomerPortal\\Expense\\ExpensesController'))->toBeFalse()
        ->and(class_exists('App\\Http\\Requests\\ExpenseRequest'))->toBeFalse()
        ->and(class_exists('App\\Http\\Requests\\ExpenseCategoryRequest'))->toBeFalse()
        ->and(class_exists('App\\Http\\Requests\\DeleteExpensesRequest'))->toBeFalse()
        ->and(class_exists('App\\Http\\Requests\\UploadExpenseReceiptRequest'))->toBeFalse()
        ->and(class_exists('App\\Http\\Resources\\ExpenseResource'))->toBeFalse()
        ->and(class_exists('App\\Http\\Resources\\ExpenseCategoryResource'))->toBeFalse()
        ->and(class_exists('App\\Http\\Resources\\ExpenseCollection'))->toBeFalse()
        ->and(class_exists('App\\Http\\Resources\\ExpenseCategoryCollection'))->toBeFalse();

    expect(class_exists('App\\Http\\Resources\\Customer\\ExpenseResource'))->toBeFalse()
        ->and(class_exists('App\\Http\\Resources\\Customer\\ExpenseCategoryResource'))->toBeFalse()
        ->and(class_exists('App\\Http\\Resources\\Customer\\ExpenseCollection'))->toBeFalse();
});

test('the purchases domain preserves company expense routes and middleware', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => preg_match('#^api/v1/(?:expenses|categories)(?:$|/)#', $route->uri()) === 1)
        ->keyBy(fn ($route): string => implode('|', $route->methods()).' '.$route->uri());

    expect($routes->keys()->sort()->values()->all())->toBe(collect([
        'DELETE api/v1/categories/{category}',
        'DELETE api/v1/expenses/{expense}',
        'GET|HEAD api/v1/categories',
        'GET|HEAD api/v1/categories/{category}',
        'GET|HEAD api/v1/expenses',
        'GET|HEAD api/v1/expenses/{expense}',
        'GET|HEAD api/v1/expenses/{expense}/show/receipt',
        'POST api/v1/categories',
        'POST api/v1/expenses',
        'POST api/v1/expenses/delete',
        'POST api/v1/expenses/{expense}/upload/receipts',
        'PUT|PATCH api/v1/categories/{category}',
        'PUT|PATCH api/v1/expenses/{expense}',
    ])->sort()->values()->all());

    foreach ($routes as $route) {
        expect($route->getActionName())
            ->toStartWith('App\\Domains\\Purchases\\Http\\Controllers\\Company\\')
            ->and($route->gatherMiddleware())->toContain('auth:sanctum', 'company', 'bouncer');
    }
});

test('the purchases domain preserves customer and receipt web routes', function () {
    $customerRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/{company}/customer/expenses'));

    expect($customerRoutes)->toHaveCount(2);

    foreach ($customerRoutes as $route) {
        expect($route->getActionName())
            ->toStartWith('App\\Domains\\Purchases\\Http\\Controllers\\CustomerPortal\\')
            ->and($route->gatherMiddleware())->toContain('auth:customer', 'customer-portal');
    }

    $receiptRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => in_array($route->uri(), [
            'reports/expenses/{expense}/download-receipt',
            'reports/expenses/{expense}/receipt',
        ], true));

    expect($receiptRoutes)->toHaveCount(2);

    foreach ($receiptRoutes as $route) {
        expect($route->getActionName())
            ->toStartWith('App\\Domains\\Purchases\\Http\\Controllers\\Company\\')
            ->and($route->gatherMiddleware())->toContain('auth:sanctum');
    }
});
