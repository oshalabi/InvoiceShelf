<?php

use App\Domains\Contacts\Http\Controllers\CustomerPortal\Auth\ForgotPasswordController;
use App\Domains\Contacts\Http\Controllers\CustomerPortal\Auth\ResetPasswordController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail']);
    Route::post('reset/password', [ResetPasswordController::class, 'reset'])->name('customer.password.reset');
});
