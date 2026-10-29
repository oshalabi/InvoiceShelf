<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Sales\Application\RecurringInvoiceService;
use App\Domains\Sales\Http\Controllers\Company\RecurringInvoiceController;
use App\Domains\Sales\Http\Requests\RecurringInvoiceRequest;
use App\Domains\Sales\Models\InvoiceItem;
use App\Domains\Sales\Models\RecurringInvoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->withHeaders([
        'company' => $user->companies()->first()->id,
    ]);
    Sanctum::actingAs(
        $user,
        ['*']
    );
});

test('get recurring invoices', function () {
    RecurringInvoice::factory()->create();

    getJson('api/v1/recurring-invoices?page=1')
        ->assertOk();
});

test('store user using a form request', function () {
    $this->assertActionUsesFormRequest(
        RecurringInvoiceController::class,
        'store',
        RecurringInvoiceRequest::class
    );
});

test('store recurring invoice', function () {
    $recurringInvoice = RecurringInvoice::factory()->raw();
    $recurringInvoice['items'] = [
        InvoiceItem::factory()->raw(),
    ];

    postJson('api/v1/recurring-invoices', $recurringInvoice)
        ->assertStatus(201);

    $recurringInvoice = collect($recurringInvoice)
        ->only([
            'frequency',
        ])
        ->toArray();

    $this->assertDatabaseHas('recurring_invoices', $recurringInvoice);
});

test('rejects a nonzero per-item placeholder tax row', function () {
    $recurringInvoice = RecurringInvoice::factory()->raw([
        'items' => [
            InvoiceItem::factory()->raw([
                'taxes' => [[
                    'tax_type_id' => 0,
                    'amount' => 1,
                ]],
            ]),
        ],
    ]);

    postJson('api/v1/recurring-invoices', $recurringInvoice)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items.0.taxes.0.amount');
});

test('allows a zero-valued per-item placeholder tax row', function () {
    $recurringInvoice = RecurringInvoice::factory()->raw([
        'items' => [
            InvoiceItem::factory()->raw([
                'taxes' => [[
                    'tax_type_id' => 0,
                    'amount' => 0,
                ]],
            ]),
        ],
    ]);

    postJson('api/v1/recurring-invoices', $recurringInvoice)
        ->assertCreated();
});

test('generated invoices retain the recurring template tax-included semantics', function () {
    $recurringInvoice = RecurringInvoice::factory()->create([
        'starts_at' => Carbon::yesterday(),
        'limit_by' => RecurringInvoice::NONE,
        'tax_included' => true,
        'sub_total' => 10000,
        'tax' => 2019,
        'total' => 10119,
        'due_amount' => 10119,
    ]);

    app(RecurringInvoiceService::class)->generateInvoice($recurringInvoice);

    $this->assertDatabaseHas('invoices', [
        'recurring_invoice_id' => $recurringInvoice->id,
        'tax_included' => 1,
        'tax' => 2019,
        'total' => 10119,
    ]);
});

test('get recurring invoice', function () {
    $recurringInvoice = RecurringInvoice::factory()->create();

    getJson("api/v1/recurring-invoices/{$recurringInvoice->id}")
        ->assertOk();
});

test('update user using a form request', function () {
    $this->assertActionUsesFormRequest(
        RecurringInvoiceController::class,
        'update',
        RecurringInvoiceRequest::class
    );
});

test('update recurring invoice', function () {
    $recurringInvoice = RecurringInvoice::factory()->create();
    $recurringInvoice['items'] = [
        InvoiceItem::factory()->raw(),
    ];

    $new_recurringInvoice = RecurringInvoice::factory()->raw();
    $new_recurringInvoice['items'] = [
        InvoiceItem::factory()->raw(),
    ];

    putJson("api/v1/recurring-invoices/{$recurringInvoice->id}", $new_recurringInvoice)
        ->assertOk();

    $new_recurringInvoice = collect($new_recurringInvoice)
        ->only([
            'frequency',
        ])
        ->toArray();

    $this->assertDatabaseHas('recurring_invoices', $new_recurringInvoice);
});

test('delete multiple recurring invoice', function () {
    $recurringInvoices = RecurringInvoice::factory()->count(3)->create();

    $data = [
        'ids' => $recurringInvoices->pluck('id'),
    ];

    postJson('api/v1/recurring-invoices/delete', $data)
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    foreach ($recurringInvoices as $recurringInvoice) {
        $this->assertModelMissing($recurringInvoice);
    }
});

test('calculate frequency for recurring invoice', function () {
    $data = [
        'frequency' => '* * 2 * *',
        'starts_at' => Carbon::now()->format('Y-m-d'),
    ];

    $queryString = http_build_query($data, '', '&');

    getJson('api/v1/recurring-invoice-frequency?'.$queryString)
        ->assertOk();
});
