<?php

namespace App\Platform\Storage\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accepts only a disk name that is present in the filesystem configuration.
 */
class FilesystemDisks implements ValidationRule
{
    public function __construct() {}

    /**
     * Reject anything that is not a registered filesystem disk.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $registered = array_keys(config('filesystems.disks'));

        if (! in_array($value, $registered)) {
            $fail('This disk is not configured as a filesystem disk.');
        }
    }
}
