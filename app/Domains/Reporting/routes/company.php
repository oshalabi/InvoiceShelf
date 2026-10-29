<?php

use App\Domains\Reporting\Http\Controllers\Company\CustomerStatementController;
use App\Domains\Reporting\Http\Controllers\Company\DashboardController;
use App\Domains\Reporting\Http\Controllers\Company\SearchController;
use App\Domains\Reporting\Http\Controllers\Company\SendCustomerStatementController;
use Illuminate\Support\Facades\Route;

// The aggregate figures behind the admin landing page.
Route::get('dashboard', DashboardController::class);
Route::get('/search', SearchController::class);
Route::get('/search/user', [SearchController::class, 'users']);
Route::get('customers/{customer}/statement', CustomerStatementController::class);
Route::post('customers/{customer}/statement/send', SendCustomerStatementController::class);
