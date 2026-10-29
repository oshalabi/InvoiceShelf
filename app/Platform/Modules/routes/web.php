<?php

use App\Platform\Modules\Http\Controllers\Assets\ScriptController;
use App\Platform\Modules\Http\Controllers\Assets\StyleController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::prefix('modules')->group(function () {
        Route::get('styles/{style}', StyleController::class);
        Route::get('scripts/{script}', ScriptController::class);
    });
});
