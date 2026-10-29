<?php

namespace App\Platform\Modules\Infrastructure;

use App\Domains\Accounts\Models\CompanySetting;
use App\Platform\Operations\Models\Setting;
use InvoiceShelf\Modules\Contracts\Host\SettingsStore;

/**
 * The module SDK intentionally deals in opaque values. Encryption and other
 * value transformations remain the responsibility of the calling module.
 */
class EloquentHostSettingsStore implements SettingsStore
{
    public function getGlobal(string $key, mixed $default = null): mixed
    {
        return Setting::getSetting($key) ?? $default;
    }

    public function putGlobal(string $key, mixed $value): void
    {
        Setting::setSetting($key, $value);
    }

    public function deleteGlobal(string $key): void
    {
        Setting::query()->where('option', $key)->delete();
    }

    public function getCompany(int $companyId, string $key, mixed $default = null): mixed
    {
        return CompanySetting::getSetting($key, $companyId) ?? $default;
    }

    public function putCompany(int $companyId, string $key, mixed $value): void
    {
        CompanySetting::setSettings([$key => $value], $companyId);
    }

    public function deleteCompany(int $companyId, string $key): void
    {
        CompanySetting::query()
            ->where('company_id', $companyId)
            ->where('option', $key)
            ->delete();
    }

    public function deleteCompanyForAll(string $key): void
    {
        CompanySetting::query()->where('option', $key)->delete();
    }
}
