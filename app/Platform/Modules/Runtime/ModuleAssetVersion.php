<?php

namespace App\Platform\Modules\Runtime;

class ModuleAssetVersion
{
    public const HASH_LENGTH = 12;

    public static function forPath(string $path): ?string
    {
        $hash = is_file($path) ? hash_file('sha256', $path) : false;

        return is_string($hash) ? substr($hash, 0, self::HASH_LENGTH) : null;
    }

    public static function forContents(string $contents): string
    {
        return substr(hash('sha256', $contents), 0, self::HASH_LENGTH);
    }
}
