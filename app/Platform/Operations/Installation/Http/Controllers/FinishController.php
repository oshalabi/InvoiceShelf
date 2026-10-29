<?php

namespace App\Platform\Operations\Installation\Http\Controllers;

use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The wizard's closing step. Nothing is persisted here — completion state is
 * tracked through the wizard-step endpoint — so acknowledging is all there is
 * to do.
 */
class FinishController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(['success' => true]);
    }
}
