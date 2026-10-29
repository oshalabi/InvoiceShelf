<?php

use App\Platform\Operations\Http\Admin\AdminDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('dashboard', [AdminDashboardController::class, 'index']);
