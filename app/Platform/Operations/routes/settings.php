<?php

use App\Platform\Operations\Http\Admin\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/settings', [SettingsController::class, 'show']);
Route::post('/settings', [SettingsController::class, 'update']);
