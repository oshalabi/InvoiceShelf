<?php

use App\Platform\Pdf\Http\Admin\FontController;
use App\Platform\Pdf\Http\Admin\PdfConfigurationController;
use Illuminate\Support\Facades\Route;

Route::get('/fonts/status', [FontController::class, 'status']);
Route::post('/fonts/{package}/install', [FontController::class, 'install']);

Route::get('/pdf/drivers', [PdfConfigurationController::class, 'getDrivers']);
Route::get('/pdf/config', [PdfConfigurationController::class, 'getEnvironment']);
Route::post('/pdf/config', [PdfConfigurationController::class, 'saveEnvironment']);
