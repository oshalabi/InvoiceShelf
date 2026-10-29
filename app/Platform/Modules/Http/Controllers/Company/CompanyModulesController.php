<?php

namespace App\Platform\Modules\Http\Controllers\Company;

use App\Platform\Http\Controller;
use App\Platform\Modules\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use InvoiceShelf\Modules\Registry as ModuleRegistry;

/**
 * Read-only company-context Active Modules index.
 *
 * Lists every module the super admin has activated on this instance
 * (Module::enabled = true) and reports whether each one has registered a
 * settings schema. The frontend uses this to render the company-context
 * "Modules" landing page with a Settings button per active module.
 *
 * Activation is instance-global; per-company customization happens through
 * settings (per CompanySetting under the module.{slug}.* prefix).
 *
 * Slug convention: nwidart stores the module's PascalCase class name in
 * `modules.name` (e.g. "SalesTaxUs"), but URLs and registry keys use the
 * kebab-case form ("sales-tax-us") for readability. We normalize via
 * Str::kebab() so module authors can call Registry::registerMenu('sales-tax-us')
 * naturally without thinking about the storage format.
 */
class CompanyModulesController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('manage module settings');

        $modules = Module::query()
            ->where('enabled', true)
            ->get()
            ->map(function (Module $module) {
                $slug = Str::kebab($module->name);
                $menu = ModuleRegistry::menuFor($slug);
                $translatedMenuTitle = $this->translateMenuTitle($menu['title'] ?? null);
                $displayName = $translatedMenuTitle ?? Str::headline($module->name);

                return [
                    'slug' => $slug,
                    'name' => $module->name,
                    'display_name' => $displayName,
                    'version' => $module->version,
                    'has_settings' => ModuleRegistry::settingsFor($slug) !== null,
                    'menu' => $menu === null
                        ? null
                        : [
                            ...$menu,
                            'title' => $translatedMenuTitle ?? $menu['title'],
                        ],
                ];
            })
            ->values();

        return response()->json(['data' => $modules]);
    }

    private function translateMenuTitle(?string $title): ?string
    {
        if ($title === null) {
            return null;
        }

        $translatedTitle = __($title);

        if (! is_string($translatedTitle) || $translatedTitle === $title) {
            return null;
        }

        return $translatedTitle;
    }
}
