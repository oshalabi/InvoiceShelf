<?php

use App\Domains\Contacts\Http\Controllers\Company\CustomersController;
use App\Domains\Contacts\Http\Controllers\Company\CustomerStatsController;
use Illuminate\Support\Facades\Route;

// The two endpoints that hang off the contacts collection but are not part of
// its resource. Both are declared first, so the literal "delete" segment can
// never be read as a {customer} key.
Route::prefix('customers')->group(function () {
    Route::post('delete', [CustomersController::class, 'delete']);
    Route::get('{customer}/stats', CustomerStatsController::class);
});

// Contact CRUD, as a full resource rather than an API one — the create and
// edit routes stay in the table even though the SPA never calls them.
Route::resources([
    'customers' => CustomersController::class,
]);
