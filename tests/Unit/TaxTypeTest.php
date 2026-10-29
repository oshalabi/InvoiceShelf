<?php

use App\Domains\Taxation\Models\TaxType;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('tax type has many taxes', function () {
    $taxtype = TaxType::factory()->hasTaxes(4)->create();

    $this->assertCount(4, $taxtype->taxes);
    $this->assertTrue($taxtype->taxes()->exists());
});

test('tax type filters by transaction type', function () {
    TaxType::factory()->create(['transaction_type' => TaxType::TRANSACTION_TYPE_SALES]);
    $purchaseTaxType = TaxType::factory()->create([
        'transaction_type' => TaxType::TRANSACTION_TYPE_PURCHASES,
    ]);

    expect(TaxType::whereTransactionType(TaxType::TRANSACTION_TYPE_PURCHASES)->get())
        ->pluck('id')
        ->all()
        ->toBe([$purchaseTaxType->id]);
});
