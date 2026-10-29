<?php

use App\Platform\Operations\Installation\Http\Controllers\AppDomainController;
use App\Platform\Operations\Installation\Http\Controllers\DatabaseConfigurationController;
use App\Platform\Operations\Installation\Http\Controllers\FilePermissionsController;
use App\Platform\Operations\Installation\Http\Controllers\FinishController;
use App\Platform\Operations\Installation\Http\Controllers\LanguagesController;
use App\Platform\Operations\Installation\Http\Controllers\LoginController;
use App\Platform\Operations\Installation\Http\Controllers\OnboardingWizardController;
use App\Platform\Operations\Installation\Http\Controllers\RequirementsController;
use Illuminate\Support\Facades\Route;

// Where the wizard left off. Both halves have to answer before any table
// exists, so they read and write the step through the settings store.
Route::controller(OnboardingWizardController::class)->group(function () {
    Route::get('wizard-step', 'getStep');
    Route::post('wizard-step', 'updateStep');
});

Route::post('/wizard-language', [OnboardingWizardController::class, 'saveLanguage']);
Route::get('/languages', [LanguagesController::class, 'languages']);

// Pre-flight panels: interpreter and extension versions, then the directories
// the application has to be able to write to.
Route::get('requirements', [RequirementsController::class, 'requirements']);
Route::get('permissions', [FilePermissionsController::class, 'permissions']);

// The database step. POST verifies the submitted credentials, writes the
// environment file and migrates; GET only hands the form its driver defaults.
Route::prefix('database')->controller(DatabaseConfigurationController::class)->group(function () {
    Route::post('config', 'saveDatabaseEnvironment');
    Route::get('config', 'getDatabaseEnvironment');
});

// Session and stateful-domain wiring, once the schema is in place.
Route::put('set-domain', AppDomainController::class);

Route::post('/login', LoginController::class);
Route::post('/finish', FinishController::class);
