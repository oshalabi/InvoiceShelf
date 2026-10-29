<?php

use App\Domains\Contacts\Models\Customer;
use App\Domains\Receivables\Application\PaymentService;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Receivables\Models\PaymentAllocation;
use App\Domains\Sales\Models\Invoice;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('payment receipt lists every allocated invoice and remaining credit', function () {
    $customer = Customer::factory()->create();
    $first = Invoice::factory()->create([
        'company_id' => $customer->company_id,
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-ALLOCATION-ONE',
    ]);
    $second = Invoice::factory()->create([
        'company_id' => $customer->company_id,
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-ALLOCATION-TWO',
    ]);
    $payment = Payment::factory()->create([
        'company_id' => $customer->company_id,
        'customer_id' => $customer->id,
        'amount' => 1000,
        'base_amount' => 1000,
    ]);
    PaymentAllocation::factory()->create([
        'payment_id' => $payment->id,
        'invoice_id' => $first->id,
        'amount' => 300,
        'base_amount' => 300,
    ]);
    PaymentAllocation::factory()->create([
        'payment_id' => $payment->id,
        'invoice_id' => $second->id,
        'amount' => 400,
        'base_amount' => 400,
    ]);

    request()->query->set('preview', true);
    $html = app(PaymentService::class)->getPdfData($payment)->render();

    expect($html)
        ->toContain('INV-ALLOCATION-ONE')
        ->toContain('INV-ALLOCATION-TWO')
        ->toContain(__('unapplied_credit'));
});
