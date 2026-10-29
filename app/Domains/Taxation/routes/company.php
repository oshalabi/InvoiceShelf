<?php

use App\Domains\Taxation\Http\Controllers\TaxTypesController;
use Illuminate\Support\Facades\Route;

Route::controller(TaxTypesController::class)
    ->name('tax-types.')
    ->group(function (): void {
        Route::get('tax-types', 'index')->name('index');
        Route::post('tax-types', 'store')->name('store');
        Route::get('tax-types/{tax_type}', 'show')->name('show');
        Route::match(['PUT', 'PATCH'], 'tax-types/{tax_type}', 'update')->name('update');
        Route::delete('tax-types/{tax_type}', 'destroy')->name('destroy');
    });
