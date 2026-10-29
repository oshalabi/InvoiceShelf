<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Sales\Models\Invoice;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;

/**
 * make:template clones a whole design, not just its entry file: the report
 * itself, the layout it extends and the stylesheet that layout includes.
 *
 * The clone used to copy one hardcoded partials/table.blade.php, which reports
 * do not have. Everything else was left to the blanket app.pdf.{type} to
 * pdf_templates::{type} rewrite, so a cloned report extended
 * pdf_templates::reports.partials.layout, a view nothing ever wrote, and died
 * with "View [reports.partials.layout] not found" on the first render.
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
    // disk is faked. Point the namespace at the same place so the cloned files
    // are the ones Blade resolves.
    View::addNamespace('pdf_templates', Storage::disk('pdf_templates')->path(''));
    View::getFinder()->flush();

    config(['pdf.driver' => 'dompdf']);

    $this->clone = function (string $type, string $name) {
        Artisan::call('make:template', ['name' => $name, '--type' => $type]);

        View::getFinder()->flush();
    };
});

function clonedTemplateFile(string $type, string $file): string
{
    return Storage::disk('pdf_templates')->path("{$type}/{$file}");
}

/**
 * Read off disk rather than hardcoded, so a partial added to the reports design
 * later is covered the moment it lands.
 *
 * @return array<int, string>
 */
function stockPartialNames(string $type): array
{
    $files = glob(dirname(__DIR__, 3)."/resources/views/app/pdf/{$type}/partials/*.blade.php");

    return array_values(array_map(
        fn ($path) => basename($path, '.blade.php'),
        $files ?: []
    ));
}

test('a cloned report gets its own copy of every partial its type ships', function () {
    ($this->clone)('reports', 'profit-loss');

    $partials = stockPartialNames('reports');

    expect($partials)->not->toBeEmpty();

    foreach ($partials as $partial) {
        expect(File::exists(clonedTemplateFile('reports', "partials/profit-loss/{$partial}.blade.php")))
            ->toBeTrue("partial {$partial} was not copied");
    }
});

test('the cloned report points at its own copies rather than at the built-ins', function () {
    ($this->clone)('reports', 'profit-loss');

    $markup = File::get(clonedTemplateFile('reports', 'profit-loss.blade.php'));

    expect($markup)->toContain('pdf_templates::reports.partials.profit-loss.layout')
        ->and($markup)->not->toContain('app.pdf.reports');
});

/**
 * The layout includes the stylesheet, so the copies have to be rewritten too.
 * Without that the copied layout still pulls in the built-in stylesheet and the
 * "own copy" property is only skin deep: editing the copy changes nothing.
 */
test('a copied partial points at the copies of the partials it includes', function () {
    ($this->clone)('reports', 'profit-loss');

    $layout = File::get(clonedTemplateFile('reports', 'partials/profit-loss/layout.blade.php'));

    expect($layout)->toContain('pdf_templates::reports.partials.profit-loss.styles')
        ->and($layout)->not->toContain('app.pdf.reports.partials.styles')
        // app.pdf.partials.* is chrome shared by every type, not part of the
        // design being cloned, so it keeps pointing at the built-in.
        ->and($layout)->toContain('app.pdf.partials.fonts');
});

test('a second clone of a different report gets separate copies', function () {
    ($this->clone)('reports', 'profit-loss');
    ($this->clone)('reports', 'expenses');

    foreach (['profit-loss', 'expenses'] as $report) {
        expect(File::exists(clonedTemplateFile('reports', "partials/{$report}/layout.blade.php")))->toBeTrue();
    }

    $layout = File::get(clonedTemplateFile('reports', 'partials/expenses/layout.blade.php'));

    expect($layout)->toContain('pdf_templates::reports.partials.expenses.styles')
        ->and($layout)->not->toContain('partials.profit-loss.');
});

/**
 * Keyed by template name, valued by route: the two sales reports are named
 * sales-customers / sales-items but are served under /reports/sales/.
 */
dataset('cloned reports', [
    'expenses' => ['expenses', 'expenses'],
    'profit-loss' => ['profit-loss', 'profit-loss'],
    'sales-customers' => ['sales-customers', 'sales/customers'],
    'sales-items' => ['sales-items', 'sales/items'],
    'tax-summary' => ['tax-summary', 'tax-summary'],
]);

test('a cloned report renders a pdf', function (string $report, string $route) {
    ($this->clone)('reports', $report);

    $response = get("/reports/{$route}/{$this->company->unique_hash}?from_date=2020-01-01&to_date=2030-12-31");

    $response->assertOk();
    expect($response->getContent())->toStartWith('%PDF-');
})->with('cloned reports');

/**
 * A marker in the copy, because a clone that silently resolved back to the
 * built-in layout would still emit a valid PDF and pass the assertion above.
 */
test('the cloned report renders through its own copied partials', function () {
    ($this->clone)('reports', 'profit-loss');

    $styles = clonedTemplateFile('reports', 'partials/profit-loss/styles.blade.php');
    File::put($styles, File::get($styles).'<!-- RENDERED BY THE CLONED STYLES -->');
    View::getFinder()->flush();

    get("/reports/profit-loss/{$this->company->unique_hash}?from_date=2020-01-01&to_date=2030-12-31&preview=true")
        ->assertOk()
        ->assertSee('RENDERED BY THE CLONED STYLES', false);
});

/**
 * The generalisation has to be a superset of the hardcoded table copy it
 * replaced: invoice and estimate designs include partials/table.blade.php at a
 * path custom templates in the wild already point at.
 */
test('a cloned invoice template still renders through its own copied table partial', function () {
    ($this->clone)('invoice', 'branded');

    expect(File::exists(clonedTemplateFile('invoice', 'partials/branded/table.blade.php')))->toBeTrue();

    $table = clonedTemplateFile('invoice', 'partials/branded/table.blade.php');
    File::put($table, File::get($table).'<!-- RENDERED BY THE CLONED TABLE -->');
    View::getFinder()->flush();

    $invoice = Invoice::factory()->hasItems(1)->create([
        'company_id' => $this->company->id,
        'template_name' => 'branded',
    ]);

    get("/invoices/pdf/{$invoice->unique_hash}?preview=true")
        ->assertOk()
        ->assertSee('RENDERED BY THE CLONED TABLE', false);
});
