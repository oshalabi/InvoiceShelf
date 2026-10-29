<?php

use App\Domains\Contacts\Http\Controllers\CountriesController;
use Illuminate\Support\Facades\Route;

Route::get('countries', CountriesController::class); // country list for the guest-facing forms
