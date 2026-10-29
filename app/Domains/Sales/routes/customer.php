<?php

use App\Domains\Sales\Http\Controllers\CustomerPortal\AcceptEstimateController;
use App\Domains\Sales\Http\Controllers\CustomerPortal\EstimatesController;
use App\Domains\Sales\Http\Controllers\CustomerPortal\InvoicesController;
use Illuminate\Support\Facades\Route;

Route::get('invoices', [InvoicesController::class, 'index']);
Route::get('invoices/{id}', [InvoicesController::class, 'show']);
Route::post('/estimate/{estimate}/status', AcceptEstimateController::class);
Route::get('estimates', [EstimatesController::class, 'index']);
Route::get('estimates/{id}', [EstimatesController::class, 'show']);
