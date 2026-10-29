<?php

use App\Domains\Accounts\Http\Controllers\Admin\CompaniesController;
use App\Domains\Accounts\Http\Controllers\Company\CompanySettingsController;
use App\Domains\Accounts\Http\Controllers\Company\MembersController;
use Illuminate\Support\Facades\Route;

Route::post('companies', [CompaniesController::class, 'store']); // gated on owning the active company
Route::post('/transfer/ownership/{user}', [CompanySettingsController::class, 'transferOwnership']);
Route::post('companies/delete', [CompaniesController::class, 'destroy']); // posted, and confirmed by name
Route::get('companies', [CompaniesController::class, 'userCompanies']);

Route::post('/members/delete', [MembersController::class, 'delete']);
Route::apiResource('/members', MembersController::class);
