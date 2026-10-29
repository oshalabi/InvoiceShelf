<?php

namespace App\Platform\Modules\Infrastructure;

use App\Domains\Accounts\Models\CompanySetting;
use App\Platform\Modules\Contracts\ModuleSettingsStore;

class EloquentModuleSettingsStore implements ModuleSettingsStore
{
    public function get(string $key, int|string|null $companyId): mixed
    {
        return CompanySetting::getSetting($key, $companyId);
    }

    public function put(array $settings, int|string|null $companyId): void
    {
        CompanySetting::setSettings($settings, $companyId);
    }

    public function deleteForModule(string $slug): void
    {
        CompanySetting::query()
            ->where('option', 'like', "module.{$slug}.%")
            ->delete();
    }
}
