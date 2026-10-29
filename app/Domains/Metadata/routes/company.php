<?php

use App\Domains\Metadata\Http\Controllers\CustomFieldsController;
use App\Domains\Metadata\Http\Controllers\NotesController;
use Illuminate\Support\Facades\Route;

// Field definitions are registered as a full resource rather than an API one:
// the create and edit routes stay in the table even though the SPA has no page
// to put them on.
Route::resources([
    'custom-fields' => CustomFieldsController::class,
]);

// The notes library takes the API set only, so no form routes are minted.
Route::apiResources([
    'notes' => NotesController::class,
]);
