<?php

use App\Domains\Accounts\Models\Company;
use App\Platform\Modules\Infrastructure\BouncerModuleAuthorization;
use App\Platform\Modules\Infrastructure\EloquentCompanyDataReader;
use App\Platform\Modules\Infrastructure\EloquentHostSettingsStore;
use App\Platform\Operations\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use InvoiceShelf\Modules\Contracts\Host\CompanyDataReader;
use InvoiceShelf\Modules\Contracts\Host\ModuleAuthorization;
use InvoiceShelf\Modules\Contracts\Host\SettingsStore;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('module service provider binds every host contract to its core adapter', function () {
    expect(app(SettingsStore::class))->toBeInstanceOf(EloquentHostSettingsStore::class)
        ->and(app(ModuleAuthorization::class))->toBeInstanceOf(BouncerModuleAuthorization::class)
        ->and(app(CompanyDataReader::class))->toBeInstanceOf(EloquentCompanyDataReader::class);
});

test('host settings store keeps opaque global values unchanged and supports defaults and deletion', function () {
    $store = new EloquentHostSettingsStore;
    $opaqueValue = 'eyJpdiI6IlJlYWxseS1ub3QtYS1wbGFpbnRleHQtdmFsdWUifQ==';

    expect($store->getGlobal('ai.api_key', 'fallback'))->toBe('fallback');

    $store->putGlobal('ai.api_key', $opaqueValue);

    expect($store->getGlobal('ai.api_key'))->toBe($opaqueValue)
        ->and(Setting::query()->where('option', 'ai.api_key')->value('value'))->toBe($opaqueValue);

    $store->deleteGlobal('ai.api_key');

    expect($store->getGlobal('ai.api_key', 'fallback'))->toBe('fallback');
});

test('host settings store scopes company values and deletion to the supplied company', function () {
    $store = new EloquentHostSettingsStore;
    $companyA = Company::firstOrFail();
    $companyB = Company::factory()->create();

    $store->putCompany($companyA->id, 'ai.api_key', 'company-a-opaque-value');
    $store->putCompany($companyB->id, 'ai.api_key', 'company-b-opaque-value');

    expect($store->getCompany($companyA->id, 'ai.api_key'))->toBe('company-a-opaque-value')
        ->and($store->getCompany($companyB->id, 'ai.api_key'))->toBe('company-b-opaque-value');

    $store->deleteCompany($companyA->id, 'ai.api_key');

    expect($store->getCompany($companyA->id, 'ai.api_key', 'fallback'))->toBe('fallback')
        ->and($store->getCompany($companyB->id, 'ai.api_key'))->toBe('company-b-opaque-value');
});

test('host settings store deletes one key across every company without touching other keys', function () {
    $store = new EloquentHostSettingsStore;
    $companyA = Company::firstOrFail();
    $companyB = Company::factory()->create();

    $store->putCompany($companyA->id, 'ai.chat_enabled', true);
    $store->putCompany($companyB->id, 'ai.chat_enabled', false);
    $store->putCompany($companyB->id, 'unrelated.setting', 'kept');

    $store->deleteCompanyForAll('ai.chat_enabled');

    expect($store->getCompany($companyA->id, 'ai.chat_enabled', 'missing'))->toBe('missing')
        ->and($store->getCompany($companyB->id, 'ai.chat_enabled', 'missing'))->toBe('missing')
        ->and($store->getCompany($companyB->id, 'unrelated.setting'))->toBe('kept');
});
