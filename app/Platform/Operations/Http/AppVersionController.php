<?php

namespace App\Platform\Operations\Http;

use App\Platform\Http\Controller;
use App\Platform\Operations\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

/**
 * Public probe describing the running build.
 *
 * Three facts, no authentication: what is on disk, which release channel the
 * updater follows, and whether the in-app updater is available at all.
 */
class AppVersionController extends Controller
{
    /**
     * @return JsonResponse
     */
    public function __invoke(Request $request)
    {
        return response()->json([
            'version' => preg_replace('~[\r\n]+~', '', File::get(base_path('version.md'))),
            'channel' => $this->releaseChannel(),
            'containerized' => (bool) config('invoiceshelf.containerized'),
        ]);
    }

    /**
     * The stored channel, self-healing: the first caller to find nothing stored
     * gets "stable" and leaves that default behind for everyone after them.
     */
    private function releaseChannel(): mixed
    {
        $stored = Setting::getSetting('updater_channel');

        if (! is_null($stored)) {
            return $stored;
        }

        Setting::setSetting('updater_channel', 'stable');

        return 'stable';
    }
}
