<?php

// Domain behavioural suite — Reporting (spec: reporting-domain-spec.md).

use App\Domains\Accounts\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    $this->owner = User::where('role', 'super admin')->first();
    $this->companyId = $this->owner->companies()->first()->id;
    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($this->owner, ['*']);

    $this->usd = DB::table('currencies')->where('code', 'USD')->value('id');
    $this->customerId = postJson('/api/v1/customers', ['name' => 'Reported', 'currency_id' => $this->usd])
        ->json('data.id');

    $this->makeInvoice = function (string $number, int $total, string $date, bool $sent = true) {
        $id = postJson('/api/v1/invoices', [
            'invoice_date' => $date, 'customer_id' => $this->customerId,
            'invoice_number' => $number, 'discount' => 0, 'discount_val' => 0,
            'sub_total' => $total, 'total' => $total, 'tax' => 0, 'template_name' => 'invoice1',
            'exchange_rate' => 2, 'currency_id' => $this->usd,
            'items' => [['name' => 'L', 'quantity' => 1, 'price' => $total, 'description' => '',
                'discount_type' => 'fixed', 'discount' => 0, 'discount_val' => 0, 'tax' => 0, 'total' => $total]],
        ])->assertSuccessful()->json('data.id');
        if ($sent) {
            postJson("/api/v1/invoices/{$id}/status", ['status' => 'SENT'])->assertOk();
        }

        return $id;
    };
});

it('derives the account summary from non-draft standard invoices and unallocated payments', function () {
    $sent = ($this->makeInvoice)('INV-AS-1', 1000, '2026-01-05');
    ($this->makeInvoice)('INV-AS-2', 500, '2026-01-06', sent: false); // draft — excluded

    postJson('/api/v1/payments', [
        'payment_date' => '2026-01-07', 'customer_id' => $this->customerId, 'amount' => 300,
        'exchange_rate' => 2, 'payment_number' => 'PAY-AS-1',
        'allocations' => [['invoice_id' => $sent, 'amount' => 200]],
    ])->assertSuccessful();

    $customer = getJson("/api/v1/customers/{$this->customerId}")->assertOk()->json('data');
    expect((int) $customer['invoice_due_amount'])->toBe(800);
    expect((int) $customer['available_credit'])->toBe(100);
    expect((int) $customer['account_balance'])->toBe(700);
    expect((int) $customer['due_amount'])->toBe(800);
});

it('builds activity statements with running balances, excluding draft invoices', function () {
    ($this->makeInvoice)('INV-ACT-1', 100, '2026-02-01');
    ($this->makeInvoice)('INV-ACT-2', 999, '2026-02-02', sent: false); // draft — excluded
    postJson('/api/v1/payments', [
        'payment_date' => '2026-02-03', 'customer_id' => $this->customerId, 'amount' => 40,
        'exchange_rate' => 2, 'payment_number' => 'PAY-ACT-1',
    ])->assertSuccessful();

    $statement = getJson("/api/v1/customers/{$this->customerId}/statement"
        .'?type=activity&from_date=2026-02-01&to_date=2026-02-28')->assertOk()->json('data');

    $entries = $statement['entries']['data'] ?? $statement['entries'];
    expect((int) $statement['opening_balance'])->toBe(0);
    expect(count($entries))->toBe(2);
    expect($entries[0]['entry_type'])->toBe('invoice');
    expect((int) $entries[0]['balance'])->toBe(100);
    expect($entries[1]['entry_type'])->toBe('payment');
    expect((int) $entries[1]['balance'])->toBe(60);
    expect((int) $statement['closing_balance'])->toBe(60);
});

it('cuts outstanding-statement allocations by their creation time, not the payment date', function () {
    $inv = ($this->makeInvoice)('INV-OUT-1', 100, '2026-01-01');
    postJson('/api/v1/payments', [
        'payment_date' => '2026-01-02', 'customer_id' => $this->customerId, 'amount' => 40,
        'exchange_rate' => 2, 'payment_number' => 'PAY-OUT-1',
        'allocations' => [['invoice_id' => $inv, 'amount' => 40]],
    ])->assertSuccessful();

    // The allocation row was created "now" (test time), long after the payment date.
    $early = getJson("/api/v1/customers/{$this->customerId}/statement?type=outstanding&as_of=2026-06-01")
        ->assertOk()->json('data');
    $invoices = collect($early['open_invoices'] ?? $early['invoices'])->keyBy('invoice_number');
    expect((int) $invoices['INV-OUT-1']['remaining_amount'])->toBe(100);

    $today = now()->toDateString();
    $late = getJson("/api/v1/customers/{$this->customerId}/statement?type=outstanding&as_of={$today}")
        ->assertOk()->json('data');
    $invoices = collect($late['open_invoices'] ?? $late['invoices'])->keyBy('invoice_number');
    expect((int) $invoices['INV-OUT-1']['remaining_amount'])->toBe(60);
});

it('empties the dashboard recent lists by ability while always returning totals', function () {
    ($this->makeInvoice)('INV-DB-1', 700, '2026-01-05');

    $abilities = fn (array $names) => array_map(fn ($a) => ['ability' => $a], $names);
    postJson('/api/v1/roles', ['name' => 'dash-only', 'abilities' => $abilities(['dashboard'])])->assertSuccessful();
    postJson('/api/v1/members', ['name' => 'Dash', 'email' => 'dash@x.test', 'password' => 'secret123',
        'companies' => [['id' => $this->companyId, 'role' => 'dash-only']]])->assertSuccessful();

    $full = getJson('/api/v1/dashboard')->assertOk()->json();
    expect(count($full['recent_due_invoices']))->toBe(1);
    expect((int) $full['total_amount_due'])->toBe(1400);

    app('auth')->forgetGuards();
    Sanctum::actingAs(User::where('email', 'dash@x.test')->first(), ['*']);
    $this->withHeaders(['company' => $this->companyId]);

    $limited = getJson('/api/v1/dashboard')->assertOk()->json();
    expect($limited['recent_due_invoices'])->toBe([]);
    expect((int) $limited['total_amount_due'])->toBe(1400);
});

it('searches users by email across the whole installation', function () {
    $countryId = DB::table('countries')->value('id');
    $eur = DB::table('currencies')->where('code', 'EUR')->value('id');
    $second = postJson('/api/v1/companies', [
        'name' => 'Elsewhere Ltd', 'currency' => $eur, 'address' => ['country_id' => $countryId],
    ])->assertSuccessful()->json('data');

    postJson('/api/v1/members', ['name' => 'Foreign Member', 'email' => 'foreign@elsewhere.test',
        'password' => 'secret123', 'companies' => [['id' => $second['id'], 'role' => 'owner']]])
        ->assertSuccessful();

    $found = collect(getJson('/api/v1/search/user?email=elsewhere')->assertOk()->json('users.data'))
        ->pluck('email');
    expect($found)->toContain('foreign@elsewhere.test');
});
