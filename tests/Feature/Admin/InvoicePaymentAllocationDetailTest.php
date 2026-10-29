<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Receivables\Models\PaymentAllocation;
use App\Domains\Sales\Models\Invoice;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::findOrFail(1);
    $this->company = $user->companies()->firstOrFail();
    $this->withHeaders(['company' => $this->company->id]);
    Sanctum::actingAs($user, ['*']);
});

test('invoice detail returns allocation rows with their payments', function () {
    $customer = Customer::factory()->create(['company_id' => $this->company->id]);
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $customer->id,
    ]);
    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $customer->id,
    ]);
    $allocation = PaymentAllocation::factory()->create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
        'amount' => 500,
        'base_amount' => 500,
    ]);

    getJson("api/v1/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('data.payment_allocations.0.id', $allocation->id)
        ->assertJsonPath('data.payment_allocations.0.payment_id', $payment->id)
        ->assertJsonPath('data.payment_allocations.0.amount', 500)
        ->assertJsonPath('data.payment_allocations.0.payment.id', $payment->id)
        ->assertJsonPath('data.payment_allocations.0.payment.payment_number', $payment->payment_number);
});
