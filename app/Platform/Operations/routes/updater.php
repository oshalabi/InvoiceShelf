<?php

use App\Platform\Operations\Http\Admin\UpdateController;
use Illuminate\Support\Facades\Route;

Route::middleware('not-containerized')->group(function () {
    Route::get('/check/update', [UpdateController::class, 'checkVersion']);
    Route::post('/update/download', [UpdateController::class, 'download']);
    Route::post('/update/unzip', [UpdateController::class, 'unzip']);
    Route::post('/update/copy', [UpdateController::class, 'copy']);
    Route::post('/update/delete', [UpdateController::class, 'delete']);
    Route::post('/update/clean', [UpdateController::class, 'clean']);
    Route::post('/update/migrate', [UpdateController::class, 'migrate']);
    Route::post('/update/finish', [UpdateController::class, 'finish']);
});
