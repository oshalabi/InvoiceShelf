<?php

use App\Domains\Accounts\Http\Controllers\Auth\LoginController;
use App\Domains\Accounts\Http\Controllers\InvitationDeclineController;
use Illuminate\Support\Facades\Route;

Route::post('login', [LoginController::class, 'login']);
Route::post('auth/logout', [LoginController::class, 'logout']);
Route::get('/invitations/{token}/decline', InvitationDeclineController::class);
