<?php

use App\Domains\Receivables\Models\Payment;
use App\Domains\Sales\Application\DocumentItemService;
use App\Domains\Sales\Application\InvoiceService;
use App\Domains\Sales\Http\Requests\InvoicesRequest;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\InvoiceItem;
use App\Domains\Taxation\Models\Tax;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('invoice has many invoice items', function () {
    $invoice = Invoice::factory()->hasItems(5)->create();

    $this->assertCount(5, $invoice->items);

    $this->assertTrue($invoice->items()->exists());
});

test('invoice has many taxes', function () {
    $invoice = Invoice::factory()->hasTaxes(5)->create();

    $this->assertCount(5, $invoice->taxes);

    $this->assertTrue($invoice->taxes()->exists());
});

test('invoice has many payments', function () {
    $invoice = Invoice::factory()->create();
    $payments = Payment::factory()->count(5)->create([
        'company_id' => $invoice->company_id,
        'customer_id' => $invoice->customer_id,
        'currency_id' => $invoice->currency_id,
    ]);

    foreach ($payments as $payment) {
        $invoice->payments()->attach($payment->id, [
            'amount' => $payment->amount,
            'base_amount' => $payment->base_amount,
        ]);
    }

    $this->assertCount(5, $invoice->fresh()->payments);

    $this->assertTrue($invoice->payments()->exists());
});

test('invoice belongs to customer', function () {
    $invoice = Invoice::factory()->forCustomer()->create();

    $this->assertTrue($invoice->customer()->exists());
});

test('get previous status', function () {
    $invoice = Invoice::factory()->create();

    $status = $invoice->getPreviousStatus();

    $this->assertEquals('DRAFT', $status);
});

test('create invoice', function () {
    $invoice = Invoice::factory()->raw();

    $item = InvoiceItem::factory()->raw();

    $invoice['items'] = [];
    array_push($invoice['items'], $item);

    $invoice['taxes'] = [];
    array_push($invoice['taxes'], Tax::factory()->raw());

    $request = new InvoicesRequest;

    $request->replace($invoice);

    $invoice_number = explode('-', $invoice['invoice_number']);
    $number_attributes['invoice_number'] = $invoice_number[0].'-'.sprintf('%06d', intval($invoice_number[1]));

    $response = app(InvoiceService::class)->create(
        attributes: $request->getInvoicePayload(),
        items: $request->input('items'),
        taxes: $request->input('taxes'),
    );

    $this->assertDatabaseHas('invoice_items', [
        'invoice_id' => $response->id,
        'name' => $item['name'],
        'description' => $item['description'],
        'total' => $item['total'],
        'quantity' => $item['quantity'],
        'discount' => $item['discount'],
        'price' => $item['price'],
    ]);

    $this->assertDatabaseHas('invoices', [
        'invoice_number' => $invoice['invoice_number'],
        'discount' => $invoice['discount'],
        'notes' => $invoice['notes'],
        'customer_id' => $invoice['customer_id'],
        'template_name' => $invoice['template_name'],
    ]);
});

test('update invoice', function () {
    $invoice = Invoice::factory()->create();

    $newInvoice = Invoice::factory()->raw();

    $item = InvoiceItem::factory()->raw([
        'invoice_id' => $invoice->id,
    ]);

    $tax = Tax::factory()->raw([
        'invoice_id' => $invoice->id,
    ]);

    $newInvoice['items'] = [];
    $newInvoice['taxes'] = [];

    array_push($newInvoice['items'], $item);
    array_push($newInvoice['taxes'], $tax);

    $request = new InvoicesRequest;

    $request->replace($newInvoice);

    $invoice_number = explode('-', $newInvoice['invoice_number']);

    $number_attributes['invoice_number'] = $invoice_number[0].'-'.sprintf('%06d', intval($invoice_number[1]));

    $response = app(InvoiceService::class)->update(
        invoice: $invoice,
        attributes: $request->getInvoicePayload(),
        items: $request->input('items'),
        taxes: $request->input('taxes'),
    );

    $this->assertDatabaseHas('invoice_items', [
        'invoice_id' => $response->id,
        'name' => $item['name'],
        'description' => $item['description'],
        'total' => $item['total'],
        'quantity' => $item['quantity'],
        'discount' => $item['discount'],
        'price' => $item['price'],
    ]);

    $this->assertDatabaseHas('invoices', [
        'invoice_number' => $newInvoice['invoice_number'],
        'discount' => $newInvoice['discount'],
        'notes' => $newInvoice['notes'],
        'customer_id' => $newInvoice['customer_id'],
        'template_name' => $newInvoice['template_name'],
    ]);
});

test('create items', function () {
    $invoice = Invoice::factory()->create();

    $items = [];

    $item = InvoiceItem::factory()->raw([
        'invoice_id' => $invoice->id,
    ]);

    array_push($items, $item);

    app(DocumentItemService::class)->createItems($invoice, $items);

    $this->assertDatabaseHas('invoice_items', [
        'invoice_id' => $invoice->id,
        'description' => $item['description'],
        'price' => $item['price'],
        'tax' => $item['tax'],
        'quantity' => $item['quantity'],
        'total' => $item['total'],
    ]);
});

test('create taxes', function () {
    $invoice = Invoice::factory()->create();

    $taxes = [];

    $tax = Tax::factory()->raw([
        'invoice_id' => $invoice->id,
    ]);

    array_push($taxes, $tax);

    app(DocumentItemService::class)->createTaxes($invoice, $taxes);

    $this->assertDatabaseHas('taxes', [
        'invoice_id' => $invoice->id,
        'name' => $tax['name'],
        'amount' => $tax['amount'],
    ]);
});

test('invoice notes preserve line breaks from editor paragraphs in pdf output', function () {
    $invoice = Invoice::factory()->create([
        'notes' => '<p>First line</p><p>Second line</p>',
    ]);

    expect($invoice->getNotes())->toContain('First line<br>Second line');
});
