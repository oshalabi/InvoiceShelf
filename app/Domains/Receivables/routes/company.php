<?php

use App\Domains\Receivables\Http\Controllers\Company\CreditAllocationsController;
use App\Domains\Receivables\Http\Controllers\Company\PaymentMethodsController;
use App\Domains\Receivables\Http\Controllers\Company\PaymentsController;
use Illuminate\Support\Facades\Route;

Route::post('customers/{customer}/credit-allocations', [CreditAllocationsController::class, 'store']);
Route::get('/payments/{payment}/send/preview', [PaymentsController::class, 'sendPreview']);
Route::post('/payments/{payment}/send', [PaymentsController::class, 'send']);
Route::put('/payments/{payment}/allocations', [PaymentsController::class, 'replaceAllocations']);
Route::post('payments/delete', [PaymentsController::class, 'delete']);

Route::apiResources([
    'payments' => PaymentsController::class,
    'payment-methods' => PaymentMethodsController::class,
]);
