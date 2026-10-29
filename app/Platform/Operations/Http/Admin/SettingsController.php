<?php

namespace App\Platform\Operations\Http\Admin;

use App\Platform\Http\Controller;
use App\Platform\Operations\Http\Requests\GetSettingRequest;
use App\Platform\Operations\Http\Requests\SettingRequest;
use App\Platform\Operations\Models\Setting;
use Illuminate\Http\JsonResponse;

/**
 * Read and write access to the instance-wide settings store.
 */
class SettingsController extends Controller
{
    /**
     * Return a single option, keyed by the option name that was asked for.
     * An option that has never been written answers with null, not a 404.
     */
    public function show(GetSettingRequest $request): JsonResponse
    {
        $this->authorize('manage settings');

        $key = $request->input('key');

        return response()->json([$key => Setting::getSetting($key)]);
    }

    /**
     * Upsert every submitted option.
     *
     * The echoed payload sits at numeric index 0 rather than under a named
     * property, so the body serialises as {"success": true, "0": {...}}.
     * Clients only read `success`; the shape is kept as it is.
     */
    public function update(SettingRequest $request): JsonResponse
    {
        $this->authorize('manage settings');

        $settings = $request->input('settings');

        Setting::setSettings($settings);

        return response()->json(['success' => true, $settings]);
    }
}
