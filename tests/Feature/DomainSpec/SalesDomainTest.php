<?php

// Domain behavioural suite — Sales (spec: sales-domain-spec.md).

use App\Domains\Accounts\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    $user = User::where('role', 'super admin')->first();
    $this->companyId = $user->companies()->first()->id;
    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($user, ['*']);

    $this->usd = DB::table('currencies')->where('code', 'USD')->value('id');
    $this->customerId = postJson('/api/v1/customers', ['name' => 'Buyer', 'currency_id' => $this->usd])
        ->json('data.id');

    $this->invoicePayload = fn (string $number, array $overrides = []) => array_merge([
        'invoice_date' => '2026-04-01', 'customer_id' => $this->customerId,
        'invoice_number' => $number, 'discount' => 0, 'discount_val' => 0,
        'sub_total' => 1, 'total' => 1, 'tax' => 0, 'template_name' => 'invoice1',
        'exchange_rate' => 2, 'currency_id' => $this->usd,
        'items' => [['name' => 'Line', 'quantity' => 1, 'price' => 1000, 'description' => '',
            'discount_type' => 'fixed', 'discount' => 0, 'discount_val' => 0, 'tax' => 0, 'total' => 1000]],
    ], $overrides);
});

it('computes totals server-side, ignoring the submitted figures', function () {
    $taxType = postJson('/api/v1/tax-types', ['name' => 'DocTax', 'calculation_type' => 'percentage', 'percent' => 10])
        ->json('data.id');

    $invoice = postJson('/api/v1/invoices', ($this->invoicePayload)('INV-T-1', [
        'discount_val' => 100,
        'sub_total' => 1, 'total' => 999999, 'tax' => 7,
        'items' => [
            ['name' => 'A', 'quantity' => 2, 'price' => 500, 'description' => '', 'discount_type' => 'fixed',
                'discount' => 0, 'discount_val' => 0, 'tax' => 0, 'total' => 1],
            ['name' => 'B', 'quantity' => 1, 'price' => 250, 'description' => '', 'discount_type' => 'fixed',
                'discount' => 0, 'discount_val' => 0, 'tax' => 0, 'total' => 1],
        ],
        'taxes' => [['tax_type_id' => $taxType, 'name' => 'DocTax', 'percent' => 10, 'amount' => 50]],
    ]))->assertSuccessful()->json('data');

    expect((int) $invoice['sub_total'])->toBe(1250);
    expect((int) $invoice['tax'])->toBe(50);
    expect((int) $invoice['total'])->toBe(1200);
    expect((int) $invoice['due_amount'])->toBe(1200);
    expect((int) DB::table('invoices')->where('id', $invoice['id'])->value('base_total'))->toBe(2400);
});

it('adds only compound taxes on top of tax-inclusive totals', function () {
    $simple = postJson('/api/v1/tax-types', ['name' => 'Simple', 'calculation_type' => 'percentage', 'percent' => 5])
        ->json('data.id');
    $compound = postJson('/api/v1/tax-types', ['name' => 'Comp', 'calculation_type' => 'percentage',
        'percent' => 3, 'compound_tax' => true])->json('data.id');

    $invoice = postJson('/api/v1/invoices', ($this->invoicePayload)('INV-T-2', [
        'tax_included' => true, 'discount_val' => 100,
        'taxes' => [
            ['tax_type_id' => $simple, 'name' => 'Simple', 'percent' => 5, 'amount' => 50],
            ['tax_type_id' => $compound, 'name' => 'Comp', 'percent' => 3, 'amount' => 30, 'compound_tax' => true],
        ],
    ]))->assertSuccessful()->json('data');

    expect((int) $invoice['sub_total'])->toBe(1000);
    expect((int) $invoice['tax'])->toBe(80);
    expect((int) $invoice['total'])->toBe(930);
});

it('refuses a tax amount without a tax type but accepts zero-amount placeholders', function () {
    postJson('/api/v1/invoices', ($this->invoicePayload)('INV-T-3', [
        'items' => [['name' => 'A', 'quantity' => 1, 'price' => 100, 'description' => '', 'discount_type' => 'fixed',
            'discount' => 0, 'discount_val' => 0, 'tax' => 0, 'total' => 100,
            'taxes' => [['tax_type_id' => null, 'amount' => 10]]]],
    ]))->assertStatus(422);

    $ok = postJson('/api/v1/invoices', ($this->invoicePayload)('INV-T-4', [
        'items' => [['name' => 'A', 'quantity' => 1, 'price' => 100, 'description' => '', 'discount_type' => 'fixed',
            'discount' => 0, 'discount_val' => 0, 'tax' => 0, 'total' => 100,
            'taxes' => [['tax_type_id' => null, 'amount' => 0]]]],
    ]))->assertSuccessful()->json('data');
    expect(DB::table('taxes')->where('invoice_id', $ok['id'])->orWhere('invoice_item_id', $ok['items'][0]['id'])->count())
        ->toBe(0);
});

it('renders serial numbers from the format placeholders with separate credit-note sequences', function () {
    postJson('/api/v1/invoices', ($this->invoicePayload)('INV-N-1'))->assertSuccessful();

    $next = getJson('/api/v1/next-number?key=invoice&userId='.$this->customerId
        .'&format='.urlencode('{{SERIES:XX}}{{DELIMITER:-}}{{SEQUENCE:4}}{{DELIMITER:/}}{{CUSTOMER_SEQUENCE:2}}'))
        ->assertOk()->json();
    expect($next['nextNumber'] ?? $next['next_number'] ?? null)->toBe('XX-0002/02');

    $cn = getJson('/api/v1/next-number?key=credit_note&userId='.$this->customerId
        .'&format='.urlencode('{{SERIES:CN}}{{DELIMITER:-}}{{SEQUENCE:4}}'))->assertOk()->json();
    expect($cn['nextNumber'] ?? $cn['next_number'] ?? null)->toBe('CN-0001');
});

it('guards invoice updates once payments exist', function () {
    $invoice = postJson('/api/v1/invoices', ($this->invoicePayload)('INV-U-1'))->json('data');
    postJson("/api/v1/invoices/{$invoice['id']}/status", ['status' => 'SENT'])->assertOk();
    postJson('/api/v1/payments', [
        'payment_date' => '2026-04-02', 'customer_id' => $this->customerId, 'amount' => 400,
        'exchange_rate' => 2, 'payment_number' => 'PAY-U-1',
        'allocations' => [['invoice_id' => $invoice['id'], 'amount' => 400]],
    ])->assertSuccessful();

    $other = postJson('/api/v1/customers', ['name' => 'Somebody Else', 'currency_id' => $this->usd])->json('data.id');
    putJson("/api/v1/invoices/{$invoice['id']}", ($this->invoicePayload)('INV-U-1', ['customer_id' => $other]))
        ->assertStatus(422)->assertJsonValidationErrors(['customer_id']);

    putJson("/api/v1/invoices/{$invoice['id']}", ($this->invoicePayload)('INV-U-1', [
        'items' => [['name' => 'Line', 'quantity' => 1, 'price' => 300, 'description' => '', 'discount_type' => 'fixed',
            'discount' => 0, 'discount_val' => 0, 'tax' => 0, 'total' => 300]],
    ]))->assertStatus(422)->assertJsonValidationErrors(['total']);
});

it('walks the credit-note guard ladder and recalculates the balance', function () {
    $invoice = postJson('/api/v1/invoices', ($this->invoicePayload)('INV-CN-1'))->json('data');
    postJson("/api/v1/invoices/{$invoice['id']}/status", ['status' => 'SENT'])->assertOk();
    $itemId = $invoice['items'][0]['id'];

    postJson("/api/v1/invoices/{$invoice['id']}/credit-note", [
        'items' => [['id' => $itemId, 'quantity' => 2]],
    ])->assertStatus(422)->assertJsonPath('errors.invoice.0', 'credit_quantity_exceeds_remaining');

    $cn = postJson("/api/v1/invoices/{$invoice['id']}/credit-note", [
        'reason' => 'partial return', 'items' => [['id' => $itemId, 'quantity' => 0.5]],
    ])->assertSuccessful()->json('data');
    expect((int) $cn['total'])->toBe(-500);

    $row = DB::table('invoices')->where('id', $invoice['id'])->first();
    expect((int) $row->due_amount)->toBe(500);
    expect($row->paid_status)->toBe('UNPAID');
    expect((bool) getJson("/api/v1/invoices/{$invoice['id']}")->json('data.allow_edit'))->toBeFalse();

    postJson("/api/v1/invoices/{$invoice['id']}/credit-note", [
        'items' => [['id' => $itemId, 'quantity' => 0.5]],
    ])->assertSuccessful();
    postJson("/api/v1/invoices/{$invoice['id']}/credit-note", [
        'items' => [['id' => $itemId, 'quantity' => 0.1]],
    ])->assertStatus(422)->assertJsonPath('errors.invoice.0', 'invoice_already_fully_credited');
});

it('deletes credit notes only together with their invoice, and blocks allocated invoices', function () {
    $invoice = postJson('/api/v1/invoices', ($this->invoicePayload)('INV-D-1'))->json('data');
    postJson("/api/v1/invoices/{$invoice['id']}/status", ['status' => 'SENT'])->assertOk();
    $cnId = postJson("/api/v1/invoices/{$invoice['id']}/credit-note", [
        'items' => [['id' => $invoice['items'][0]['id'], 'quantity' => 0.25]],
    ])->assertSuccessful()->json('data.id');

    postJson('/api/v1/invoices/delete', ['ids' => [$invoice['id']]])->assertStatus(422);
    postJson('/api/v1/invoices/delete', ['ids' => [$invoice['id'], $cnId]])->assertOk();

    $paid = postJson('/api/v1/invoices', ($this->invoicePayload)('INV-D-2'))->json('data');
    postJson("/api/v1/invoices/{$paid['id']}/status", ['status' => 'SENT'])->assertOk();
    postJson('/api/v1/payments', [
        'payment_date' => '2026-04-02', 'customer_id' => $this->customerId, 'amount' => 100,
        'exchange_rate' => 2, 'payment_number' => 'PAY-D-1',
        'allocations' => [['invoice_id' => $paid['id'], 'amount' => 100]],
    ])->assertSuccessful();
    // The request layer's relation rule fires before the service-level guard.
    postJson('/api/v1/invoices/delete', ['ids' => [$paid['id']]])
        ->assertStatus(422)->assertJsonValidationErrors(['ids.0']);
});

it('whitelists invoice status changes and requires settlement for completion', function () {
    $invoice = postJson('/api/v1/invoices', ($this->invoicePayload)('INV-ST-1'))->json('data');

    postJson("/api/v1/invoices/{$invoice['id']}/status", ['status' => 'VIEWED'])
        ->assertStatus(422)->assertJsonValidationErrors(['status']);
    postJson("/api/v1/invoices/{$invoice['id']}/status", ['status' => 'COMPLETED'])
        ->assertStatus(422)->assertJsonPath('errors.status.0', 'invoice_must_be_settled_before_completion');
});

it('applies any submitted estimate status without validation — the documented quirk', function () {
    $estimate = postJson('/api/v1/estimates', [
        'estimate_date' => '2026-04-01', 'expiry_date' => '2026-05-01', 'customer_id' => $this->customerId,
        'estimate_number' => 'EST-Q-1', 'discount' => 0, 'discount_val' => 0,
        'sub_total' => 100, 'total' => 100, 'tax' => 0, 'template_name' => 'estimate1',
        'exchange_rate' => 2, 'currency_id' => $this->usd,
        'items' => [['name' => 'L', 'quantity' => 1, 'price' => 100, 'description' => '', 'discount_type' => 'fixed',
            'discount' => 0, 'discount_val' => 0, 'tax' => 0, 'total' => 100]],
    ])->assertSuccessful()->json('data');

    postJson("/api/v1/estimates/{$estimate['id']}/status", ['status' => 'BANANAS'])
        ->assertOk()->assertJson(['success' => true]);
    expect(DB::table('estimates')->where('id', $estimate['id'])->value('status'))->toBe('BANANAS');
});

it('clones invoices as fresh drafts and refuses to clone credit notes', function () {
    $invoice = postJson('/api/v1/invoices', ($this->invoicePayload)('INV-CL-1'))->json('data');
    postJson("/api/v1/invoices/{$invoice['id']}/status", ['status' => 'SENT'])->assertOk();

    $clone = postJson("/api/v1/invoices/{$invoice['id']}/clone")->assertSuccessful()->json('data');
    expect($clone['status'])->toBe('DRAFT');
    expect($clone['invoice_number'])->not->toBe('INV-CL-1');
    expect((int) $clone['total'])->toBe(1000);

    $cnId = postJson("/api/v1/invoices/{$invoice['id']}/credit-note", [
        'items' => [['id' => $invoice['items'][0]['id'], 'quantity' => 0.5]],
    ])->json('data.id');
    postJson("/api/v1/invoices/{$cnId}/clone")->assertStatus(422);
});

it('marks overdue invoices daily, skipping drafts and credit notes', function () {
    $due = postJson('/api/v1/invoices', ($this->invoicePayload)('INV-O-1', [
        'invoice_date' => '2026-01-01', 'due_date' => '2026-01-15',
    ]))->json('data');
    postJson("/api/v1/invoices/{$due['id']}/status", ['status' => 'SENT'])->assertOk();

    $draft = postJson('/api/v1/invoices', ($this->invoicePayload)('INV-O-2', [
        'invoice_date' => '2026-01-01', 'due_date' => '2026-01-15',
    ]))->json('data');

    Artisan::call('check:invoices:status');

    expect((bool) DB::table('invoices')->where('id', $due['id'])->value('overdue'))->toBeTrue();
    expect((bool) DB::table('invoices')->where('id', $draft['id'])->value('overdue'))->toBeFalse();
});
