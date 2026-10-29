<?php

use App\Domains\Contacts\Models\Customer;
use App\Domains\Metadata\Contracts\CustomFieldValueWriter;
use App\Domains\Metadata\Models\CustomField;
use App\Domains\Metadata\Models\CustomFieldValue;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('custom field value belongs to company', function () {
    $fieldValue = CustomFieldValue::factory()->create();

    $this->assertTrue($fieldValue->company()->exists());
});

test('custom field value belongs to custom field', function () {
    $fieldValue = CustomFieldValue::factory()->forCustomField()->create();

    $this->assertTrue($fieldValue->customField()->exists());
});

test('custom field values are attached and updated through the metadata contract', function () {
    $customField = CustomField::factory()->create([
        'model_type' => 'Customer',
        'type' => 'Input',
    ]);
    $customer = Customer::factory()->create([
        'company_id' => $customField->company_id,
    ]);
    $writer = app(CustomFieldValueWriter::class);

    $writer->attach($customer, [[
        'id' => $customField->id,
        'value' => 'First value',
    ]]);

    expect($customer->fields()->sole()->string_answer)->toBe('First value');

    $writer->update($customer, [[
        'id' => $customField->id,
        'value' => 'Updated value',
    ]]);

    expect($customer->fields()->sole()->string_answer)->toBe('Updated value');
});
