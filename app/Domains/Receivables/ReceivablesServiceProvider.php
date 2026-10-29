<?php

namespace App\Domains\Receivables;

use App\Adapters\Receivables\LaravelPaymentEmailSender;
use App\Adapters\Receivables\MoneyPaymentExchangeRateRecorder;
use App\Adapters\Receivables\SalesInvoiceBalanceUpdater;
use App\Adapters\Receivables\SalesPaymentNumberAssigner;
use App\Domains\Receivables\Application\PaymentService;
use App\Domains\Receivables\Console\RestoreLegacyPaymentLinks;
use App\Domains\Receivables\Contracts\InvoiceBalanceUpdater;
use App\Domains\Receivables\Contracts\PaymentEmailSender;
use App\Domains\Receivables\Contracts\PaymentExchangeRateRecorder;
use App\Domains\Receivables\Contracts\PaymentNumberAssigner;
use App\Domains\Receivables\Contracts\PaymentPdfDataProvider;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Receivables\Models\PaymentMethod;
use App\Domains\Receivables\Policies\PaymentMethodPolicy;
use App\Domains\Receivables\Policies\PaymentPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ReceivablesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentPdfDataProvider::class, PaymentService::class);
        $this->app->bind(InvoiceBalanceUpdater::class, SalesInvoiceBalanceUpdater::class);
        $this->app->bind(PaymentNumberAssigner::class, SalesPaymentNumberAssigner::class);
        $this->app->bind(PaymentExchangeRateRecorder::class, MoneyPaymentExchangeRateRecorder::class);
        $this->app->bind(PaymentEmailSender::class, LaravelPaymentEmailSender::class);
    }

    public function boot(): void
    {
        $this->commands([
            RestoreLegacyPaymentLinks::class,
        ]);

        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(PaymentMethod::class, PaymentMethodPolicy::class);
        $abilities = [
            'send payment' => 'send',
            'delete multiple payments' => 'deleteMultiple',
        ];
        foreach ($abilities as $ability => $method) {
            Gate::define($ability, [PaymentPolicy::class, $method]);
        }
    }
}
