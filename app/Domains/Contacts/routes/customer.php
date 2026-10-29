<?php

use App\Domains\Contacts\Http\Controllers\CountriesController;
use App\Domains\Contacts\Http\Controllers\CustomerPortal\BootstrapController;
use App\Domains\Contacts\Http\Controllers\CustomerPortal\DashboardController;
use App\Domains\Contacts\Http\Controllers\CustomerPortal\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('bootstrap', BootstrapController::class); // everything the portal SPA needs at boot
Route::get('dashboard', DashboardController::class); // landing figures for the signed-in contact
Route::post('/profile', [ProfileController::class, 'updateProfile']);
Route::get('/me', [ProfileController::class, 'getUser']);
Route::get('countries', CountriesController::class); // country list behind the address fields
