<?php

use App\Domains\Money\Http\Controllers\CurrenciesController;
use App\Domains\Money\Http\Controllers\ExchangeRateProviderController;
use Illuminate\Support\Facades\Route;

Route::get('currencies', CurrenciesController::class);
Route::get('/currencies/used', [ExchangeRateProviderController::class, 'usedCurrenciesWithoutRate']);
Route::post('/currencies/bulk-update-exchange-rate', [ExchangeRateProviderController::class, 'bulkUpdate']);
Route::get('/currencies/{currency}/exchange-rate', [ExchangeRateProviderController::class, 'getRate']);
Route::get('/currencies/{currency}/active-provider', [ExchangeRateProviderController::class, 'activeProvider']);
Route::get('/used-currencies', [ExchangeRateProviderController::class, 'usedCurrencies']);
Route::get('/supported-currencies', [ExchangeRateProviderController::class, 'supportedCurrencies']);
Route::prefix('/exchange-rate-providers')->name('exchange-rate-providers.')->group(function () {
    Route::get('/', [ExchangeRateProviderController::class, 'index'])->name('index');
    Route::post('/', [ExchangeRateProviderController::class, 'store'])->name('store');
    Route::get('/{exchange_rate_provider}', [ExchangeRateProviderController::class, 'show'])->name('show');
    Route::match(['PUT', 'PATCH'], '/{exchange_rate_provider}', [ExchangeRateProviderController::class, 'update'])->name('update');
    Route::delete('/{exchange_rate_provider}', [ExchangeRateProviderController::class, 'destroy'])->name('destroy');
});
