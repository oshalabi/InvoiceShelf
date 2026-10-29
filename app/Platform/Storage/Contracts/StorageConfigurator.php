<?php

namespace App\Platform\Storage\Contracts;

interface StorageConfigurator
{
    public function applyGlobalConfig(): void;
}
