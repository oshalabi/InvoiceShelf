<?php

use App\Domains\Purchases\Http\Controllers\Company\ExpensesController;
use Illuminate\Support\Facades\Route;

Route::get('/expenses/{expense}/download-receipt', [ExpensesController::class, 'downloadReceipt']);
Route::get('/expenses/{expense}/receipt', [ExpensesController::class, 'showReceipt']);
