<?php

use App\Domains\Receivables\Http\Controllers\CustomerPortal\PaymentMethodController;
use App\Domains\Receivables\Http\Controllers\CustomerPortal\PaymentsController;
use Illuminate\Support\Facades\Route;

Route::get('payments', [PaymentsController::class, 'index']);
Route::get('payments/{id}', [PaymentsController::class, 'show']);
// Every method on the company's books is offered here, unfiltered.
Route::get('payment-method', PaymentMethodController::class);
