<?php

namespace App\Platform\Modules\Marketplace;

use JsonException;

class CanonicalJson
{
    /**
     * @throws JsonException
     */
    public static function encode(array $value): string
    {
        return json_encode(
            self::sort($value),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        );
    }

    private static function sort(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $item) {
            $value[$key] = self::sort($item);
        }

        return $value;
    }
}
