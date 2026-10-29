<?php

namespace App\Platform\Operations\Installation\Application;

/**
 * Reports whether the writable directories the application relies on are at
 * least as permissive as the installer requires.
 */
class FilePermissionChecker
{
    protected array $results = [];

    public function __construct()
    {
        $this->results = [
            'permissions' => [],
            'errors' => null,
        ];
    }

    /**
     * Walk a map of relative folder path => required octal mode. Entries are
     * reported in the order given; "errors" stays null until something fails.
     */
    public function check(array $folders): array
    {
        foreach ($folders as $folder => $required) {
            $granted = $this->modeOf($folder) >= $required;

            $this->results['permissions'][] = [
                'folder' => $folder,
                'permission' => $required,
                'isSet' => $granted,
            ];

            if (! $granted) {
                $this->results['errors'] = true;
            }
        }

        return $this->results;
    }

    /**
     * The last four octal digits of the folder's mode, e.g. "0775". Both this
     * and the requirement are numeric strings, so the caller's comparison is
     * made on their numeric value.
     */
    private function modeOf(string $folder): string
    {
        return substr(sprintf('%o', fileperms(base_path($folder))), -4);
    }
}
