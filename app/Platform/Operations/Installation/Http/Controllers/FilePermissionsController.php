<?php

namespace App\Platform\Operations\Installation\Http\Controllers;

use App\Platform\Http\Controller;
use App\Platform\Operations\Installation\Application\FilePermissionChecker;
use Illuminate\Http\JsonResponse;

class FilePermissionsController extends Controller
{
    protected FilePermissionChecker $permissions;

    public function __construct(FilePermissionChecker $checker)
    {
        $this->permissions = $checker;
    }

    /**
     * Second wizard gate: the writability of the folders listed in
     * config/installer.php.
     */
    public function permissions(): JsonResponse
    {
        return response()->json([
            'permissions' => $this->permissions->check(
                config('installer.permissions')
            ),
        ]);
    }
}
