<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Receivables\Models\Payment;
use App\Platform\Pdf\Rendering\PdfTemplateUtils;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;

/**
 * Payment receipts and reports have no template picker, so overriding one means
 * dropping a same-named file into storage/app/templates/pdf/{type}/ and having
 * it win. Both used to be hardcoded to app.pdf.*, so there was no way to change
 * them short of editing files inside the image.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->company = $user->companies()->first();
    $this->withHeaders(['company' => $this->company->id]);
    Sanctum::actingAs($user, ['*']);

    Storage::fake('pdf_templates');
    Storage::fake('public');

    // The disk and the view namespace are registered separately, and only the
    // disk is faked. Point the namespace at the same place so a template written
    // here is the one Blade resolves.
    View::addNamespace('pdf_templates', Storage::disk('pdf_templates')->path(''));
    View::getFinder()->flush();

    config(['pdf.driver' => 'dompdf']);

    $this->override = function (string $type, string $name, string $markup) {
        $dir = Storage::disk('pdf_templates')->path($type);
        File::ensureDirectoryExists($dir);
        File::put("{$dir}/{$name}.blade.php", $markup);
        View::getFinder()->flush();
    };
});

test('the built-in view is used when nothing overrides it', function () {
    expect(PdfTemplateUtils::resolveView('payment', 'payment'))->toBe('app.pdf.payment.payment');
    expect(PdfTemplateUtils::resolveView('reports', 'expenses'))->toBe('app.pdf.reports.expenses');
});

test('a custom file takes over', function () {
    ($this->override)('payment', 'payment', '<html></html>');

    expect(PdfTemplateUtils::resolveView('payment', 'payment'))->toBe('pdf_templates::payment.payment');
});

test('an overridden payment receipt is what actually renders', function () {
    ($this->override)('payment', 'payment', '<html><body>OVERRIDDEN RECEIPT {{ $payment->payment_number }}</body></html>');

    $payment = Payment::factory()->create(['company_id' => $this->company->id]);

    get("/payments/pdf/{$payment->unique_hash}?preview=true")
        ->assertOk()
        ->assertSee('OVERRIDDEN RECEIPT')
        ->assertSee($payment->payment_number);
});

test('an overridden report is what actually renders', function () {
    ($this->override)('reports', 'expenses', '<html><body>OVERRIDDEN REPORT for {{ $company->name }}</body></html>');

    get("/reports/expenses/{$this->company->unique_hash}?from_date=2020-01-01&to_date=2030-12-31&preview=true")
        ->assertOk()
        ->assertSee('OVERRIDDEN REPORT')
        ->assertSee($this->company->name);
});

/**
 * The override receives the same shared data the built-in does, so a custom file
 * can use every variable the original template used.
 */
test('an overridden report still renders as a pdf', function () {
    ($this->override)('reports', 'expenses', '<html><body>{{ $currency->name }} {{ $from_date }}</body></html>');

    $response = get("/reports/expenses/{$this->company->unique_hash}?from_date=2020-01-01&to_date=2030-12-31");

    $response->assertOk();
    expect($response->getContent())->toStartWith('%PDF-');
});
