<?php

namespace App\Platform\Operations\Http\Company;

use App\Platform\Http\Controller;
use App\Support\Formatting\DateFormatter;
use App\Support\Formatting\TimeFormatter;
use App\Support\Formatting\TimeZones;
use Illuminate\Http\JsonResponse;

class FormatsController extends Controller
{
    public function dateFormats(): JsonResponse
    {
        return response()->json(['date_formats' => DateFormatter::get_list()]);
    }

    public function timeFormats(): JsonResponse
    {
        return response()->json([
            'time_formats' => TimeFormatter::get_list(),
        ]);
    }

    public function timezones(): JsonResponse
    {
        return response()->json(['time_zones' => TimeZones::get_list()]);
    }
}
