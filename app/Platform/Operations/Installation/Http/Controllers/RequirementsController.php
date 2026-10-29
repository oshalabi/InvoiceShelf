<?php

namespace App\Platform\Operations\Installation\Http\Controllers;

use App\Platform\Http\Controller;
use App\Platform\Operations\Installation\Application\RequirementsChecker;
use Illuminate\Http\JsonResponse;

class RequirementsController extends Controller
{
    protected RequirementsChecker $requirements;

    public function __construct(RequirementsChecker $checker)
    {
        $this->requirements = $checker;
    }

    /**
     * First wizard gate: the interpreter version block plus the per-extension
     * verdicts drawn from config/installer.php.
     */
    public function requirements(): JsonResponse
    {
        return response()->json([
            'phpSupportInfo' => $this->requirements->checkPHPVersion(
                config('installer.core.minPhpVersion')
            ),
            'requirements' => $this->requirements->check(
                config('installer.requirements')
            ),
        ]);
    }
}
