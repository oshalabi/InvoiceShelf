<?php

use App\Platform\Operations\Installation\Http\Controllers\SessionLoginController;
use Illuminate\Support\Facades\Route;

// The wizard shell. It carries a name because the not-installed gate sends
// every other page here.
Route::middleware('redirect-if-installed')
    ->get('/installation', fn () => view('app'))
    ->name('install');

// The Vue Router renders the wizard steps. This catch-all keeps deep links
// and hard refreshes inside the installation SPA from returning a 404.
Route::get('/installation/{vue?}', function () {
    return view('app');
})->where('vue', '.*')
    ->middleware(['redirect-if-installed']);

// Trades the wizard's bearer token for a browser session, so a finished
// install lands on an authenticated page rather than the login form.
Route::post('/installation/session-login', SessionLoginController::class)
    ->middleware(['redirect-if-installed', 'auth:sanctum']);
