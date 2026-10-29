<?php

use App\Domains\Catalog\Http\Controllers\ItemsController;
use App\Domains\Catalog\Http\Controllers\UnitsController;
use Illuminate\Support\Facades\Route;

Route::controller(ItemsController::class)->group(function () {
    // Bulk removal is not one of the resource verbs, so it is declared on its
    // own -- and ahead of them, to stay clear of the `items/{item}` patterns.
    Route::post('items/delete', 'delete');
});
Route::resource('items', ItemsController::class);
Route::resource('units', UnitsController::class);
