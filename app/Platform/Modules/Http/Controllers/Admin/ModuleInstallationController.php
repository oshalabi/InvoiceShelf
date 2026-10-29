<?php

namespace App\Platform\Modules\Http\Controllers\Admin;

use App\Platform\Http\Controller;
use App\Platform\Modules\Http\Requests\InstallMarketplaceModuleRequest;
use App\Platform\Modules\Http\Requests\UninstallMarketplaceModuleRequest;
use App\Platform\Modules\Marketplace\MarketplaceInstaller;
use App\Platform\Modules\Marketplace\MarketplaceUninstaller;
use Illuminate\Http\JsonResponse;

class ModuleInstallationController extends Controller
{
    public function install(InstallMarketplaceModuleRequest $request, MarketplaceInstaller $installer): JsonResponse
    {
        $this->authorize('manage modules');

        $response = $installer->install(
            $request->string('slug')->toString(),
            $request->string('version')->toString(),
            $request->string('channel')->toString() ?: (string) config('invoiceshelf.marketplace.channel', 'stable'),
        );

        return response()->json($response, $response['success'] ? 200 : 422);
    }

    public function uninstall(string $module, UninstallMarketplaceModuleRequest $request, MarketplaceUninstaller $uninstaller): JsonResponse
    {
        $this->authorize('manage modules');

        $response = $uninstaller->uninstall(
            $module,
            $request->boolean('remove_data'),
            $request->string('confirmation')->toString() ?: null,
        );

        $status = match ($response['error'] ?? null) {
            'module_not_installed' => 404,
            'operation_in_progress', 'module_runtime_missing', 'dependent_modules_installed' => 409,
            default => $response['success'] ? 200 : 422,
        };

        return response()->json($response, $status);
    }
}
