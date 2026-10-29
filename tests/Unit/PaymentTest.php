<?php

use App\Domains\Receivables\Models\Payment;
use App\Domains\Receivables\Models\PaymentAllocation;
use App\Domains\Sales\Models\Invoice;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('payment has invoice allocations', function () {
    $payment = Payment::factory()->create();
    $invoice = Invoice::factory()->create([
        'company_id' => $payment->company_id,
        'customer_id' => $payment->customer_id,
        'currency_id' => $payment->currency_id,
    ]);
    PaymentAllocation::factory()->create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
    ]);

    $this->assertTrue($payment->invoices()->exists());
});

test('payment belongs to customer', function () {
    $payment = Payment::factory()->forCustomer()->create();

    $this->assertTrue($payment->customer()->exists());
});

test('payment belongs to payment method', function () {
    $payment = Payment::factory()->forPaymentMethod()->create();

    $this->assertTrue($payment->paymentMethod()->exists());
});
