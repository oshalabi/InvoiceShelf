<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Receivables\Models\PaymentAllocation;
use App\Domains\Reporting\Mail\SendCustomerStatementMail;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Mail\Models\EmailLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    Artisan::call('db:seed', ['--force' => true, '--class' => 'DatabaseSeeder']);
    Artisan::call('db:seed', ['--force' => true, '--class' => 'DemoSeeder']);

    $this->user = User::findOrFail(1);
    $this->company = $this->user->companies()->firstOrFail();
    $this->withHeaders(['company' => $this->company->id]);
    Sanctum::actingAs($this->user, ['*']);
});

function statementCustomer(): Customer
{
    return Customer::factory()->create([
        'company_id' => test()->company->id,
    ]);
}

function statementInvoice(Customer $customer, string $date, array $attributes = []): Invoice
{
    return Invoice::factory()->create(array_merge([
        'company_id' => $customer->company_id,
        'customer_id' => $customer->id,
        'invoice_date' => $date,
        'due_date' => $date,
        'type' => Invoice::TYPE_INVOICE,
        'status' => Invoice::STATUS_SENT,
        'total' => 1000,
        'base_total' => 1000,
        'due_amount' => 1000,
        'base_due_amount' => 1000,
    ], $attributes));
}

function statementPayment(Customer $customer, string $date, int $amount = 1000): Payment
{
    return Payment::factory()->create([
        'company_id' => $customer->company_id,
        'customer_id' => $customer->id,
        'payment_date' => $date,
        'amount' => $amount,
        'base_amount' => $amount,
    ]);
}

test('activity statements calculate opening and closing balances and include draft credit notes', function () {
    $customer = statementCustomer();
    statementInvoice($customer, '2026-01-20', ['total' => 500, 'base_total' => 500]);
    $invoice = statementInvoice($customer, '2026-02-10', ['total' => 1000, 'base_total' => 1000]);
    $creditNote = statementInvoice($customer, '2026-02-10', [
        'type' => Invoice::TYPE_CREDIT_NOTE,
        'status' => Invoice::STATUS_DRAFT,
        'total' => -200,
        'base_total' => -200,
        'due_amount' => 0,
        'base_due_amount' => 0,
    ]);
    $payment = statementPayment($customer, '2026-02-10', 300);
    statementInvoice($customer, '2026-02-11', [
        'status' => Invoice::STATUS_DRAFT,
        'total' => 999,
        'base_total' => 999,
    ]);

    $response = getJson("/api/v1/customers/{$customer->id}/statement?from_date=2026-02-01&to_date=2026-02-28");

    $response->assertOk()
        ->assertJsonPath('data.opening_balance', 500)
        ->assertJsonPath('data.closing_balance', 1000)
        ->assertJsonPath('data.entries.0.id', $invoice->id)
        ->assertJsonPath('data.entries.0.entry_type', 'invoice')
        ->assertJsonPath('data.entries.1.id', $creditNote->id)
        ->assertJsonPath('data.entries.1.entry_type', 'credit_note')
        ->assertJsonPath('data.entries.2.id', $payment->id)
        ->assertJsonPath('data.entries.2.entry_type', 'payment')
        ->assertJsonCount(3, 'data.entries');
});

test('outstanding statements respect allocation timing for historical as-of dates', function () {
    $customer = statementCustomer();
    $invoice = statementInvoice($customer, '2026-01-10');
    $payment = statementPayment($customer, '2026-01-15');
    $allocation = PaymentAllocation::create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
        'amount' => 1000,
        'base_amount' => 1000,
    ]);
    $allocation->forceFill(['created_at' => Carbon::parse('2026-02-05 09:00:00')])->save();

    getJson("/api/v1/customers/{$customer->id}/statement?type=outstanding&as_of=2026-01-31")
        ->assertOk()
        ->assertJsonPath('data.invoice_due_amount', 1000)
        ->assertJsonPath('data.available_credit', 1000)
        ->assertJsonPath('data.account_balance', 0)
        ->assertJsonCount(1, 'data.invoices')
        ->assertJsonCount(1, 'data.credits');

    getJson("/api/v1/customers/{$customer->id}/statement?type=outstanding&as_of=2026-02-28")
        ->assertOk()
        ->assertJsonPath('data.invoice_due_amount', 0)
        ->assertJsonPath('data.available_credit', 0)
        ->assertJsonCount(0, 'data.invoices')
        ->assertJsonCount(0, 'data.credits');
});

test('customer account aggregates preserve due amount compatibility and show available credit', function () {
    $customer = statementCustomer();
    statementInvoice($customer, '2026-01-10', ['due_amount' => 600, 'base_due_amount' => 600]);
    $payment = statementPayment($customer, '2026-01-15', 500);
    PaymentAllocation::create([
        'payment_id' => $payment->id,
        'invoice_id' => statementInvoice($customer, '2026-01-11')->id,
        'amount' => 200,
        'base_amount' => 200,
    ]);

    getJson("/api/v1/customers/{$customer->id}")
        ->assertOk()
        ->assertJsonPath('data.due_amount', 1600)
        ->assertJsonPath('data.invoice_due_amount', 1600)
        ->assertJsonPath('data.available_credit', 300)
        ->assertJsonPath('data.account_balance', 1300);
});

test('statements require both customer and financial-report abilities', function () {
    $customer = statementCustomer();
    $user = User::factory()->create();
    $user->companies()->attach($this->company->id);

    BouncerFacade::scope()->to($this->company->id);
    BouncerFacade::allow($user)->to('view-customer', Customer::class);
    Sanctum::actingAs($user, ['*']);

    getJson("/api/v1/customers/{$customer->id}/statement")
        ->assertForbidden();
});

test('statements cannot be read across companies', function () {
    $otherCompany = Company::factory()->create();
    $customer = Customer::factory()->create(['company_id' => $otherCompany->id]);

    getJson("/api/v1/customers/{$customer->id}/statement")
        ->assertForbidden();
});

test('sending a statement attaches the live PDF and logs it against the customer', function () {
    Mail::fake();
    config([
        'mail.from.address' => 'configured@example.test',
        'mail.from.name' => 'Configured Sender',
    ]);
    $customer = statementCustomer();
    statementInvoice($customer, '2026-01-10');

    postJson("/api/v1/customers/{$customer->id}/statement/send", [
        'subject' => 'January statement',
        'body' => 'Your statement is attached.',
        'to' => $customer->email,
    ])->assertOk();

    $sent = null;
    Mail::assertSent(SendCustomerStatementMail::class, function (SendCustomerStatementMail $mail) use ($customer, &$sent) {
        $sent = $mail;

        return $mail->data['customer']->is($customer)
            && $mail->data['from'] === 'configured@example.test'
            && $mail->data['from_name'] === 'Configured Sender'
            && str_ends_with($mail->data['filename'], '.pdf')
            && $mail->data['pdf']->output() !== '';
    });

    $sent->build();

    expect(EmailLog::query()
        ->where('mailable_type', $customer->getMorphClass())
        ->where('mailable_id', $customer->id)
        ->where('from', 'configured@example.test')
        ->exists())->toBeTrue();
});

test('statement email ignores a submitted sender address', function () {
    Mail::fake();
    config([
        'mail.from.address' => 'configured@example.test',
        'mail.from.name' => 'Configured Sender',
    ]);
    $customer = statementCustomer();

    postJson("/api/v1/customers/{$customer->id}/statement/send", [
        'subject' => 'Statement',
        'body' => 'Your statement is attached.',
        'from' => 'spoofed@example.test',
        'to' => $customer->email,
    ])->assertOk();

    Mail::assertSent(SendCustomerStatementMail::class, fn (SendCustomerStatementMail $mail) => $mail->data['from'] === 'configured@example.test'
        && $mail->data['from_name'] === 'Configured Sender');
});

test('the authenticated report route streams the customer statement PDF', function () {
    $customer = statementCustomer();
    statementInvoice($customer, '2026-01-10');

    get("/reports/customers/{$customer->id}/statement?from_date=2026-01-01&to_date=2026-01-31")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('outstanding statement PDF preview includes account totals', function () {
    $customer = statementCustomer();
    statementInvoice($customer, '2026-01-10');
    statementPayment($customer, '2026-01-15', 250);

    get("/reports/customers/{$customer->id}/statement?type=outstanding&as_of=2026-01-31&preview=1")
        ->assertOk()
        ->assertSee('Gross invoice due')
        ->assertSee('Available credit')
        ->assertSee('Net account balance');
});
