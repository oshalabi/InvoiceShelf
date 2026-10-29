<?php

use App\Support\DocumentTotals;

test('sums item totals from price and quantity, ignoring client-supplied item totals', function () {
    $totals = DocumentTotals::compute(
        [['price' => 10000, 'quantity' => 10, 'total' => 1, 'taxes' => []]],
        [], 0, 'NO', false, 'NO'
    );

    expect($totals['sub_total'])->toBe(100000)
        ->and($totals['total'])->toBe(100000)
        ->and($totals['tax'])->toBe(0);
});

test('applies document discount and document-level tax', function () {
    $totals = DocumentTotals::compute(
        [['price' => 1000, 'quantity' => 2]],
        [['amount' => 150]],
        500, 'NO', false, 'NO'
    );

    expect($totals['sub_total'])->toBe(2000)
        ->and($totals['tax'])->toBe(150)
        ->and($totals['total'])->toBe(1650);
});

test('uses per-item taxes (not document taxes) when tax_per_item is YES', function () {
    $totals = DocumentTotals::compute(
        [
            ['price' => 1000, 'quantity' => 1, 'taxes' => [['tax_type_id' => 1, 'amount' => 100]]],
            ['price' => 2000, 'quantity' => 1, 'taxes' => [['tax_type_id' => 2, 'amount' => 200]]],
        ],
        [['amount' => 9999]],
        0, 'YES', false, 'YES'
    );

    expect($totals['sub_total'])->toBe(3000)
        ->and($totals['tax'])->toBe(300)
        ->and($totals['total'])->toBe(3300);
});

test('excludes tax from total when tax_included', function () {
    $totals = DocumentTotals::compute(
        [['price' => 1000, 'quantity' => 1]],
        [['amount' => 100]],
        0, 'NO', true, 'NO'
    );

    expect($totals['total'])->toBe(1000)->and($totals['tax'])->toBe(100);
});

test('applies per-item discount only when discount_per_item is YES', function () {
    $with = DocumentTotals::compute(
        [['price' => 1000, 'quantity' => 2, 'discount_val' => 300]],
        [], 0, 'NO', false, 'YES'
    );
    $without = DocumentTotals::compute(
        [['price' => 1000, 'quantity' => 2, 'discount_val' => 300]],
        [], 0, 'NO', false, 'NO'
    );

    expect($with['sub_total'])->toBe(1700)
        ->and($without['sub_total'])->toBe(2000);
});

test('supports negative quantities', function () {
    $totals = DocumentTotals::compute(
        [
            ['price' => 100, 'quantity' => -2],
            ['price' => 50, 'quantity' => 1],
            ['price' => 75, 'quantity' => 0],
        ],
        [], 0, 'NO', false, 'NO'
    );

    expect($totals['sub_total'])->toBe(-150)->and($totals['total'])->toBe(-150);
});

test('calculates exclusive document-level compound tax totals', function () {
    $totals = DocumentTotals::compute(
        [['price' => 10000, 'quantity' => 1]],
        [['amount' => 1900], ['amount' => 119, 'compound_tax' => true]],
        0, 'NO', false, 'NO'
    );

    expect($totals['tax'])->toBe(2019)
        ->and($totals['total'])->toBe(12019);
});

test('keeps simple taxes inside an inclusive document total while adding compound taxes', function () {
    $totals = DocumentTotals::compute(
        [['price' => 11900, 'quantity' => 1]],
        [['amount' => 1900], ['amount' => 119, 'compound_tax' => true]],
        0, 'NO', true, 'NO'
    );

    expect($totals['tax'])->toBe(2019)
        ->and($totals['total'])->toBe(12019);
});

test('calculates exclusive per-item compound tax totals and ignores the UI placeholder', function () {
    $totals = DocumentTotals::compute(
        [[
            'price' => 10000,
            'quantity' => 1,
            'taxes' => [
                ['tax_type_id' => 1, 'amount' => 1900],
                ['tax_type_id' => 2, 'amount' => 119, 'compound_tax' => true],
                ['tax_type_id' => 0, 'amount' => 0],
            ],
        ]],
        [], 0, 'YES', false, 'NO'
    );

    expect($totals['tax'])->toBe(2019)
        ->and($totals['total'])->toBe(12019);
});

test('keeps simple per-item taxes inside an inclusive total while adding compound taxes', function () {
    $totals = DocumentTotals::compute(
        [[
            'price' => 11900,
            'quantity' => 1,
            'taxes' => [
                ['tax_type_id' => 1, 'amount' => 1900],
                ['tax_type_id' => 2, 'amount' => 119, 'compound_tax' => true],
            ],
        ]],
        [], 0, 'YES', true, 'NO'
    );

    expect($totals['tax'])->toBe(2019)
        ->and($totals['total'])->toBe(12019);
});
