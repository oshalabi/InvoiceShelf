<?php

use App\Domains\Sales\Http\Controllers\CustomerPortal\EstimatePdfController;
use App\Domains\Sales\Http\Controllers\CustomerPortal\InvoicePdfController;
use Illuminate\Support\Facades\Route;

Route::get('/invoices/{email_log:token}', [InvoicePdfController::class, 'getInvoice']);
Route::get('/invoices/view/{email_log:token}', [InvoicePdfController::class, 'getPdf'])->name('invoice');
Route::get('/estimates/{email_log:token}', [EstimatePdfController::class, 'getEstimate']);
Route::get('/estimates/view/{email_log:token}', [EstimatePdfController::class, 'getPdf'])->name('estimate');
