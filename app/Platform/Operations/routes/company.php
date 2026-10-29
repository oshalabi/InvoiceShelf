<?php

use App\Platform\Operations\Http\Company\BootstrapController;
use App\Platform\Operations\Http\Company\ConfigController;
use App\Platform\Operations\Http\Company\FormatsController;
use Illuminate\Support\Facades\Route;

Route::get('bootstrap', BootstrapController::class);
Route::get('/config', ConfigController::class);
Route::get('/timezones', [FormatsController::class, 'timezones']);
Route::get('/date/formats', [FormatsController::class, 'dateFormats']);
Route::get('/time/formats', [FormatsController::class, 'timeFormats']);
Route::get('/current-company', [BootstrapController::class, 'currentCompany']);
