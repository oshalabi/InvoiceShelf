<?php

use App\Domains\Money\Models\Currency;

/**
 * format_money_pdf() renders the amount that lands in a PDF, so the sign has to
 * lead the whole string: credit notes and credit-only report periods made
 * negative figures ordinary, and formatting the raw value put the minus between
 * the symbol and the digits ("$-24,738.00").
 *
 * The currencies are built in memory (there is no CurrencyFactory) so these
 * assertions describe the formatter alone, not the seeded currency table.
 */
function pdfCurrency(array $attributes = []): Currency
{
    return new Currency(array_merge([
        'name' => 'US Dollar',
        'code' => 'USD',
        'symbol' => '$',
        'precision' => 2,
        'thousand_separator' => ',',
        'decimal_separator' => '.',
        'swap_currency_symbol' => false,
    ], $attributes));
}

function pdfSymbol(string $symbol = '$'): string
{
    return '<span style="font-family: DejaVu Sans;">'.$symbol.'</span>';
}

test('a positive amount keeps the symbol against the digits', function () {
    expect(format_money_pdf(2473800, pdfCurrency()))
        ->toBe(pdfSymbol().'24,738.00');
});

test('a negative amount leads with the sign, ahead of the symbol', function () {
    // The defect: this used to render "$-24,738.00".
    expect(format_money_pdf(-2473800, pdfCurrency()))
        ->toBe('-'.pdfSymbol().'24,738.00');
});

test('a positive amount keeps the trailing symbol against the digits when swapped', function () {
    expect(format_money_pdf(2473800, pdfCurrency(['swap_currency_symbol' => true])))
        ->toBe('24,738.00'.pdfSymbol());
});

test('a negative amount leads with the sign when the symbol trails', function () {
    expect(format_money_pdf(-2473800, pdfCurrency(['swap_currency_symbol' => true])))
        ->toBe('-24,738.00'.pdfSymbol());
});

test('zero carries no sign', function (bool $swap) {
    $currency = pdfCurrency(['swap_currency_symbol' => $swap]);

    $expected = $swap ? '0.00'.pdfSymbol() : pdfSymbol().'0.00';

    expect(format_money_pdf(0, $currency))->toBe($expected)
        ->and(format_money_pdf(-0, $currency))->toBe($expected)
        ->and(format_money_pdf(-0.0, $currency))->toBe($expected);
})->with([true, false]);

test('an amount that rounds away at the currency precision carries no sign', function (bool $swap) {
    // Decided on the formatted digits, not on the raw value: a tenth of a cent
    // is negative but prints as zero, and "-$0.00" is not a number anyone owes.
    $currency = pdfCurrency(['swap_currency_symbol' => $swap]);

    $expected = $swap ? '0.00'.pdfSymbol() : pdfSymbol().'0.00';

    expect(format_money_pdf(-0.4, $currency))->toBe($expected);
})->with([true, false]);

test('a zero-precision currency drops the decimals and still signs correctly', function () {
    $yen = pdfCurrency([
        'name' => 'Japanese Yen',
        'code' => 'JPY',
        'symbol' => '¥',
        'precision' => 0,
    ]);

    expect(format_money_pdf(2473800, $yen))->toBe(pdfSymbol('¥').'24,738')
        ->and(format_money_pdf(-2473800, $yen))->toBe('-'.pdfSymbol('¥').'24,738')
        // A single cent is below this currency's precision, so it is not a
        // negative amount once formatted.
        ->and(format_money_pdf(-1, $yen))->toBe(pdfSymbol('¥').'0');
});

test('a comma decimal separator is unaffected by the sign', function () {
    $euro = pdfCurrency([
        'name' => 'Euro',
        'code' => 'EUR',
        'symbol' => '€',
        'thousand_separator' => '.',
        'decimal_separator' => ',',
        'swap_currency_symbol' => true,
    ]);

    expect(format_money_pdf(2473800, $euro))->toBe('24.738,00'.pdfSymbol('€'))
        ->and(format_money_pdf(-2473800, $euro))->toBe('-24.738,00'.pdfSymbol('€'))
        ->and(format_money_pdf(-40, $euro))->toBe('-0,40'.pdfSymbol('€'))
        ->and(format_money_pdf(0, $euro))->toBe('0,00'.pdfSymbol('€'));
});

test('the same input types the templates pass are still accepted', function () {
    $currency = pdfCurrency();

    expect(format_money_pdf(null, $currency))->toBe(pdfSymbol().'0.00')
        ->and(format_money_pdf('-2473800', $currency))->toBe('-'.pdfSymbol().'24,738.00')
        ->and(format_money_pdf(-2473800.0, $currency))->toBe('-'.pdfSymbol().'24,738.00')
        ->and(format_money_pdf(-2473850.5, $currency))->toBe('-'.pdfSymbol().'24,738.51');
});

test('the symbol keeps its DejaVu Sans span so it renders in any font', function () {
    expect(format_money_pdf(-2473800, pdfCurrency()))
        ->toContain('<span style="font-family: DejaVu Sans;">$</span>')
        // The sign sits outside the span, in the document font.
        ->toStartWith('-<span');
});
