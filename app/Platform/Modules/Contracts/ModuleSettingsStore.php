<?php

namespace App\Platform\Modules\Contracts;

interface ModuleSettingsStore
{
    public function get(string $key, int|string|null $companyId): mixed;

    /** @param array<string, string> $settings */
    public function put(array $settings, int|string|null $companyId): void;

    public function deleteForModule(string $slug): void;
}
