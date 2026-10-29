<?php

use App\Platform\Pdf\Rendering\DompdfDriver;
use App\Platform\Pdf\Rendering\GotenbergPdfDriver;
use App\Platform\Pdf\Rendering\PdfPageSetup;
use Barryvdh\DomPDF\PDF;

/**
 * The report templates carry no inset of their own: they set
 * `.sub-container { padding: 0px 20px }` and nothing else, and were drawn
 * against dompdf's built-in 1.2cm page margin. Once DompdfDriver started
 * injecting an @page rule from config, the document default of 0 reached them
 * and their content landed flush against the paper edge.
 *
 * That zero has to stay for documents (invoice2 and estimate2 bleed a header
 * band to the edge), so the reports get a margin of their own instead.
 */
beforeEach(function () {
    config([
        'pdf.page.paper_width' => '210mm',
        'pdf.page.paper_height' => '297mm',
        'pdf.page.orientation' => 'portrait',
        'pdf.page.margin_top' => '0',
        'pdf.page.margin_right' => '0',
        'pdf.page.margin_bottom' => '0',
        'pdf.page.margin_left' => '0',
        'pdf.page.report_margin' => '1.2cm',
    ]);
});

test('reports render with the configured report margin on all four sides', function () {
    config(['pdf.page.report_margin' => '2cm']);

    $page = PdfPageSetup::forReports();

    expect($page->marginTop)->toBe('2cm')
        ->and($page->marginRight)->toBe('2cm')
        ->and($page->marginBottom)->toBe('2cm')
        ->and($page->marginLeft)->toBe('2cm')
        ->and($page->marginCss())->toBe('2cm 2cm 2cm 2cm');
});

test('reports keep the configured paper size and orientation', function () {
    config([
        'pdf.page.paper_width' => '8.5in',
        'pdf.page.paper_height' => '14in',
        'pdf.page.orientation' => 'landscape',
    ]);

    $page = PdfPageSetup::forReports();

    expect($page->width)->toBe('8.5in')
        ->and($page->height)->toBe('14in')
        ->and($page->isLandscape())->toBeTrue();
});

/**
 * The whole point of the separate key: an operator who sets document margins to
 * suit their own invoice template must not silently reflow every report too.
 */
test('the document margins do not leak into the report page', function () {
    config([
        'pdf.page.margin_top' => '40mm',
        'pdf.page.margin_right' => '30mm',
        'pdf.page.margin_bottom' => '20mm',
        'pdf.page.margin_left' => '10mm',
    ]);

    expect(PdfPageSetup::forReports()->marginCss())->toBe('1.2cm 1.2cm 1.2cm 1.2cm')
        ->and(PdfPageSetup::fromConfig()->marginCss())->toBe('40mm 30mm 20mm 10mm');
});

test('a blank or unset report margin falls back to dompdf\'s old default', function ($value) {
    config(['pdf.page.report_margin' => $value]);

    expect(PdfPageSetup::forReports()->marginTop)->toBe('1.2cm');
})->with([
    'unset' => [null],
    'blank' => [''],
    'whitespace' => ['   '],
]);

/**
 * An env typo should fail loudly rather than render at some other size, and the
 * message has to name the key so the fix is obvious.
 */
test('a malformed report margin is rejected', function () {
    config(['pdf.page.report_margin' => '12']);

    expect(fn () => PdfPageSetup::forReports())
        ->toThrow(InvalidArgumentException::class, 'pdf.page.report_margin');
});

test('withUniformMargin returns a new page and leaves the original alone', function () {
    $original = PdfPageSetup::fromConfig();
    $inset = $original->withUniformMargin('15mm');

    expect($inset)->not->toBe($original)
        ->and($inset->marginCss())->toBe('15mm 15mm 15mm 15mm')
        ->and($original->marginCss())->toBe('0 0 0 0')
        ->and($inset->width)->toBe($original->width)
        ->and($inset->height)->toBe($original->height)
        ->and($inset->orientation)->toBe($original->orientation);
});

test('withUniformMargin rejects a value that is not a length', function () {
    expect(fn () => PdfPageSetup::fromConfig()->withUniformMargin('wide'))
        ->toThrow(InvalidArgumentException::class, 'Invalid PDF page margin');
});

test('a bare zero is still an acceptable uniform margin', function () {
    expect(PdfPageSetup::fromConfig()->withUniformMargin('0')->marginCss())->toBe('0 0 0 0');
});

/**
 * dompdf has no margin API, so the injected @page rule is the only thing that
 * carries the margin. It has to be built from the page it was handed, not read
 * back out of config, or passing one would be a no-op.
 */
test('dompdf injects the page setup it is given rather than the configured one', function () {
    $method = new ReflectionMethod(DompdfDriver::class, 'withPageMargins');

    $html = $method->invoke(
        new DompdfDriver,
        '<html><head></head><body></body></html>',
        PdfPageSetup::fromConfig()->withUniformMargin('1.2cm')
    );

    expect($html)->toContain('@page { margin: 1.2cm 1.2cm 1.2cm 1.2cm; }');
});

/**
 * And that loadView() actually threads the page through to that injection
 * rather than reading config again, which the reflection test above cannot see.
 * The wrapper is captured instead of asserting on the rendered bytes: dompdf
 * stamps a CreationDate, so two renders differ whatever the margins are.
 */
test('dompdf renders the report page setup it is handed', function () {
    $driver = new class extends DompdfDriver
    {
        public string $html = '';

        protected function wrapper(): PDF
        {
            $pdf = Mockery::mock(PDF::class);
            $pdf->shouldReceive('setPaper')->andReturnSelf();
            $pdf->shouldReceive('loadHTML')->andReturnUsing(function (string $html) use ($pdf) {
                $this->html = $html;

                return $pdf;
            });

            return $pdf;
        }
    };

    $driver->loadView('app.pdf.partials.fonts', [], PdfPageSetup::forReports());

    expect($driver->html)->toContain('@page { margin: 1.2cm 1.2cm 1.2cm 1.2cm; }');
});

test('gotenberg sends the margins of the page setup it is given', function () {
    config(['pdf.connections.gotenberg.host' => 'http://gotenberg.example.com:3000']);

    $body = (string) (new GotenbergPdfDriver)
        ->buildRequest('app.pdf.partials.fonts', [], PdfPageSetup::forReports())
        ->getBody();

    expect($body)->toContain('marginTop')
        ->toContain('1.2cm')
        ->toContain('210mm');
});
