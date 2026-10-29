<?php

use App\Domains\Receivables\Http\Controllers\PublicPaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/payments/{email_log:token}', [PublicPaymentController::class, 'getPayment']);
Route::get('/payments/view/{email_log:token}', [PublicPaymentController::class, 'getPdf'])->name('payment');
