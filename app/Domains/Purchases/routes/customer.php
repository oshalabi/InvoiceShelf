<?php

use App\Domains\Purchases\Http\Controllers\CustomerPortal\ExpensesController;
use Illuminate\Support\Facades\Route;

Route::get('expenses', [ExpensesController::class, 'index']);
Route::get('expenses/{id}', [ExpensesController::class, 'show']);
