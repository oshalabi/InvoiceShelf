<?php

namespace App\Platform\Operations\Installation\Http\Controllers;

use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;

class LanguagesController extends Controller
{
    /**
     * Display the languages page.
     *
     * @return JsonResponse
     */
    public function languages()
    {
        return response()->json([
            'languages' => config('invoiceshelf.languages'),
        ]);
    }
}
