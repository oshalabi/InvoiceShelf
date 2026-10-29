<?php

use App\Domains\Contacts\Http\Controllers\CustomerPortal\Auth\LoginController;
use App\Domains\Contacts\Http\Controllers\CustomerPortal\Auth\LogoutController;
use Illuminate\Support\Facades\Route;

Route::post('/{company:slug}/customer/login', LoginController::class);
Route::post('/{company:slug}/customer/logout', LogoutController::class);
