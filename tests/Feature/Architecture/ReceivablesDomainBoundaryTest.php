<?php

use App\Adapters\Receivables\LaravelPaymentEmailSender;
use App\Adapters\Receivables\MoneyPaymentExchangeRateRecorder;
use App\Adapters\Receivables\SalesInvoiceBalanceUpdater;
use App\Adapters\Receivables\SalesPaymentNumberAssigner;
use App\Domains\Receivables\Application\PaymentService;
use App\Domains\Receivables\Contracts\InvoiceBalanceUpdater;
use App\Domains\Receivables\Contracts\PaymentEmailSender;
use App\Domains\Receivables\Contracts\PaymentExchangeRateRecorder;
use App\Domains\Receivables\Contracts\PaymentNumberAssigner;
use App\Domains\Receivables\Contracts\PaymentPdfDataProvider;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Receivables\Models\PaymentMethod;
use App\Domains\Receivables\Policies\PaymentMethodPolicy;
use App\Domains\Receivables\Policies\PaymentPolicy;
use App\Domains\Receivables\ReceivablesServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

test('the receivables domain owns payment behavior and authorization', function () {
    expect(app()->getProviders(ReceivablesServiceProvider::class))->toHaveCount(1)
        ->and(app(PaymentPdfDataProvider::class))->toBeInstanceOf(PaymentService::class)
        ->and(app(InvoiceBalanceUpdater::class))->toBeInstanceOf(SalesInvoiceBalanceUpdater::class)
        ->and(app(PaymentNumberAssigner::class))->toBeInstanceOf(SalesPaymentNumberAssigner::class)
        ->and(app(PaymentExchangeRateRecorder::class))->toBeInstanceOf(MoneyPaymentExchangeRateRecorder::class)
        ->and(app(PaymentEmailSender::class))->toBeInstanceOf(LaravelPaymentEmailSender::class)
        ->and(Gate::getPolicyFor(Payment::class))->toBeInstanceOf(PaymentPolicy::class)
        ->and(Gate::getPolicyFor(PaymentMethod::class))->toBeInstanceOf(PaymentMethodPolicy::class)
        ->and(Gate::has('send payment'))->toBeTrue()
        ->and(Gate::has('delete multiple payments'))->toBeTrue();

    foreach ([
        'App\\Services\\Document\\PaymentService',
        'App\\Services\\Document\\PaymentAllocationService',
        'App\\Policies\\PaymentPolicy',
        'App\\Policies\\PaymentMethodPolicy',
        'App\\Jobs\\GeneratePaymentPdfJob',
        'App\\Mail\\SendPaymentMail',
        'App\\Http\\Controllers\\Company\\Payment\\PaymentsController',
        'App\\Http\\Controllers\\Company\\Payment\\PaymentMethodsController',
        'App\\Http\\Controllers\\Company\\Payment\\CreditAllocationsController',
        'App\\Http\\Controllers\\CustomerPortal\\Payment\\PaymentsController',
        'App\\Http\\Controllers\\CustomerPortal\\Payment\\PaymentMethodController',
        'App\\Http\\Controllers\\CustomerPortal\\PaymentPdfController',
        'App\\Http\\Requests\\PaymentRequest',
        'App\\Http\\Requests\\PaymentMethodRequest',
        'App\\Http\\Requests\\DeletePaymentsRequest',
        'App\\Http\\Requests\\ReplacePaymentAllocationsRequest',
        'App\\Http\\Requests\\CreditAllocationRequest',
        'App\\Http\\Requests\\SendPaymentRequest',
        'App\\Http\\Resources\\PaymentResource',
        'App\\Http\\Resources\\PaymentMethodResource',
        'App\\Http\\Resources\\PaymentCollection',
        'App\\Http\\Resources\\PaymentMethodCollection',
        'App\\Http\\Resources\\TransactionResource',
        'App\\Http\\Resources\\Customer\\PaymentResource',
        'App\\Http\\Resources\\Customer\\PaymentMethodResource',
        'App\\Http\\Resources\\Customer\\PaymentCollection',
        'App\\Http\\Resources\\Customer\\PaymentMethodCollection',
        'App\\Http\\Resources\\Customer\\TransactionResource',
    ] as $legacyClass) {
        expect(class_exists($legacyClass))->toBeFalse();
    }
});

test('the receivables domain preserves company payment routes and middleware', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => preg_match('#^api/v1/(?:payments|payment-methods)(?:$|/)#', $route->uri()) === 1
            || $route->uri() === 'api/v1/customers/{customer}/credit-allocations')
        ->keyBy(fn ($route): string => implode('|', $route->methods()).' '.$route->uri());

    expect($routes->keys()->sort()->values()->all())->toBe(collect([
        'DELETE api/v1/payment-methods/{payment_method}',
        'DELETE api/v1/payments/{payment}',
        'GET|HEAD api/v1/payment-methods',
        'GET|HEAD api/v1/payment-methods/{payment_method}',
        'GET|HEAD api/v1/payments',
        'GET|HEAD api/v1/payments/{payment}',
        'GET|HEAD api/v1/payments/{payment}/send/preview',
        'POST api/v1/customers/{customer}/credit-allocations',
        'POST api/v1/payment-methods',
        'POST api/v1/payments',
        'POST api/v1/payments/delete',
        'POST api/v1/payments/{payment}/send',
        'PUT api/v1/payments/{payment}/allocations',
        'PUT|PATCH api/v1/payment-methods/{payment_method}',
        'PUT|PATCH api/v1/payments/{payment}',
    ])->sort()->values()->all());

    foreach ($routes as $route) {
        expect($route->getActionName())
            ->toStartWith('App\\Domains\\Receivables\\Http\\Controllers\\Company\\')
            ->and($route->gatherMiddleware())->toContain('auth:sanctum', 'company', 'bouncer');
    }
});

test('the receivables domain preserves customer and pdf routes', function () {
    $customerRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/{company}/customer/payments')
            || $route->uri() === 'api/v1/{company}/customer/payment-method');

    expect($customerRoutes)->toHaveCount(3);

    foreach ($customerRoutes as $route) {
        expect($route->getActionName())
            ->toStartWith('App\\Domains\\Receivables\\Http\\Controllers\\CustomerPortal\\')
            ->and($route->gatherMiddleware())->toContain('auth:customer', 'customer-portal');
    }

    $pdfRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => in_array($route->uri(), [
            'payments/pdf/{payment}',
            'customer/payments/{email_log}',
            'customer/payments/view/{email_log}',
        ], true));

    expect($pdfRoutes)->toHaveCount(3);

    foreach ($pdfRoutes as $route) {
        expect($route->getActionName())->toStartWith('App\\Domains\\Receivables\\Http\\Controllers\\');
    }
});
