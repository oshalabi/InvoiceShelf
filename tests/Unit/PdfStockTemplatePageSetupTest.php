<?php

use Illuminate\Support\Str;

/**
 * Stock templates must leave page geometry to the driver. An html margin
 * overrides dompdf's injected @page rule, while Chromium applies its default
 * body margin unless it is reset. Invoice and estimate headers also have to
 * remain in the printable content box when an operator enables page margins.
 */
function stockTemplateCssRules(string $source, string $selector): string
{
    preg_match(
        '/'.preg_quote($selector, '/').'\s*\{(?<rules>[^}]*)\}/',
        $source,
        $matches
    );

    return $matches['rules'] ?? '';
}

/**
 * A report that has been migrated to the shared layout keeps no stylesheet of
 * its own, so the rules to assert against live in the partials it pulls in.
 */
function stockTemplateSource(string $template): string
{
    $source = file_get_contents(resource_path("views/app/pdf/{$template}.blade.php"));

    if (! str_contains($source, "@extends('app.pdf.reports.partials.layout')")) {
        return $source;
    }

    return $source
        .file_get_contents(resource_path('views/app/pdf/reports/partials/layout.blade.php'))
        .file_get_contents(resource_path('views/app/pdf/reports/partials/styles.blade.php'));
}

test('stock templates reset body margins without overriding page margins', function (string $template) {
    $source = stockTemplateSource($template);
    $bodyRules = stockTemplateCssRules($source, 'body');

    expect($bodyRules)->toContain('margin: 0px;')
        ->and($source)
        ->not->toContain('html {');
})->with([
    'invoice/invoice1',
    'invoice/invoice2',
    'invoice/invoice3',
    'estimate/estimate1',
    'estimate/estimate2',
    'estimate/estimate3',
    'payment/payment',
    'reports/expenses',
    'reports/profit-loss',
    'reports/sales-customers',
    'reports/sales-items',
    'reports/tax-summary',
]);

/**
 * The five reports began as copies of one 2018 stylesheet and drifted apart.
 * A migrated report contributes content only: chrome and rules come from the
 * shared partials, so the copies cannot start diverging again.
 */
test('migrated reports carry no stylesheet of their own', function (string $template) {
    $source = file_get_contents(resource_path("views/app/pdf/{$template}.blade.php"));

    expect($source)->toContain("@extends('app.pdf.reports.partials.layout')")
        ->not->toContain('<style');
})->with([
    'reports/expenses',
    'reports/profit-loss',
    'reports/sales-customers',
    'reports/sales-items',
    'reports/tax-summary',
]);

/**
 * What the drifted copies were full of: a property set twice in one rule, and
 * `padding-top: 10px; padding-right: 30px; padding: 0px;`, where the shorthand
 * silently cancels both longhands.
 */
test('the shared report stylesheet has no duplicate or self-cancelling declarations', function () {
    $css = file_get_contents(resource_path('views/app/pdf/reports/partials/styles.blade.php'));
    $css = preg_replace('#/\*.*?\*/#s', '', Str::between($css, '<style type="text/css">', '</style>'));

    preg_match_all('/\{(?<declarations>[^}]*)\}/', $css, $matches);

    foreach ($matches['declarations'] as $declarations) {
        $properties = [];

        foreach (explode(';', $declarations) as $declaration) {
            if (str_contains($declaration, ':')) {
                $properties[] = trim(Str::before($declaration, ':'));
            }
        }

        expect($properties)->toEqual(array_unique($properties));

        foreach (['padding', 'margin'] as $shorthand) {
            $longhands = array_filter(
                $properties,
                fn (string $property) => str_starts_with($property, $shorthand.'-')
            );

            expect(in_array($shorthand, $properties, true) && $longhands !== [])->toBeFalse();
        }
    }
});

test('stock invoice and estimate headers never move above the printable content box', function (string $template) {
    $source = file_get_contents(resource_path("views/app/pdf/{$template}.blade.php"));
    $headerRules = stockTemplateCssRules($source, '.header-container');

    expect($headerRules)->toContain('position: relative;')
        ->not->toMatch('/(?:top|margin-top):\\s*-\\d+/');
})->with([
    'invoice/invoice1',
    'invoice/invoice2',
    'invoice/invoice3',
    'estimate/estimate1',
    'estimate/estimate2',
    'estimate/estimate3',
]);

/**
 * The stock templates set `table { border-collapse: collapse }`, and CSS says
 * padding does not apply to a table in that mode. dompdf applies it anyway;
 * Chromium follows the spec and drops it, which put the two renderers 22.5pt
 * apart on each side of the items table. The spacing belongs on a plain block
 * both engines treat the same.
 *
 * The horizontal inset must land on `.items-table-inset`, which wraps only the
 * table, and not on `.items-table-wrapper`, which wraps the whole partial: the
 * `hr` and the totals block below carry their own insets already, and stacking a
 * second one on top is what pushed the totals in from the table's right edge.
 */
test('the items table carries no padding of its own', function (string $template) {
    $source = file_get_contents(resource_path("views/app/pdf/{$template}.blade.php"));

    expect(stockTemplateCssRules($source, '.items-table'))->not->toContain('padding')
        ->and(stockTemplateCssRules($source, '.items-table-inset'))->toContain('padding-left');
})->with([
    'invoice/invoice1',
    'invoice/invoice2',
    'invoice/invoice3',
    'estimate/estimate1',
    'estimate/estimate2',
    'estimate/estimate3',
]);

test('the wrapper around the whole table partial spaces it vertically only', function (string $template) {
    $rules = stockTemplateCssRules(
        file_get_contents(resource_path("views/app/pdf/{$template}.blade.php")),
        '.items-table-wrapper'
    );

    expect($rules)->toContain('padding-top')
        ->not->toContain('padding-left')
        ->not->toContain('padding-right');
})->with([
    'invoice/invoice1',
    'invoice/invoice2',
    'invoice/invoice3',
    'estimate/estimate1',
    'estimate/estimate2',
    'estimate/estimate3',
]);

test('stock invoice and estimate templates expose the shared item-table hook', function (string $template) {
    $source = file_get_contents(resource_path("views/app/pdf/{$template}.blade.php"));

    expect($source)->toContain('class="items-table-wrapper"')
        ->toContain("@include('app.pdf.".dirname($template).".partials.table')");
})->with([
    'invoice/invoice1',
    'invoice/invoice2',
    'invoice/invoice3',
    'estimate/estimate1',
    'estimate/estimate2',
    'estimate/estimate3',
]);
