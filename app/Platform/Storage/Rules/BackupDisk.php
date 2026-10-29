<?php

namespace App\Platform\Storage\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accepts only a disk the backup package is configured to write archives to.
 */
class BackupDisk implements ValidationRule
{
    public function __construct() {}

    /**
     * Reject anything outside the configured backup destinations.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $destinations = config('backup.backup.destination.disks');

        if (! in_array($value, $destinations)) {
            $fail('This disk is not configured as a backup disk.');
        }
    }
}
