<?php

use App\Domains\Accounts\Http\Controllers\Admin\UsersController;
use Illuminate\Support\Facades\Route;

Route::post('stop-impersonating', [UsersController::class, 'stopImpersonating']);
