<?php

namespace App\Platform\Storage\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * Accepts only a path that names a zip archive.
 */
class PathToZip implements ValidationRule
{
    public function __construct() {}

    /**
     * Reject any path that does not carry the zip extension.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (Str::endsWith($value, '.zip')) {
            return;
        }

        $fail('The given value must be a path to a zip file.');
    }
}
