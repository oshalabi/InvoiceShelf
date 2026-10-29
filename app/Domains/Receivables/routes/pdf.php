<?php

use App\Domains\Receivables\Http\Controllers\PaymentPdfController;
use Illuminate\Support\Facades\Route;

Route::get('/payments/pdf/{payment:unique_hash}', PaymentPdfController::class);
