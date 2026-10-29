<?php

use App\Platform\Storage\Http\BackupsController;
use App\Platform\Storage\Http\DiskController;
use Illuminate\Support\Facades\Route;

// Both registries take the API resource set, so no create/edit form routes are minted.
Route::apiResources([
    'backups' => BackupsController::class,
    'disks' => DiskController::class,
]);

Route::get('download-backup', [BackupsController::class, 'download']);

Route::get('disk/drivers', [DiskController::class, 'getDiskDrivers']);
Route::get('/disk/purposes', [DiskController::class, 'getDiskPurposes']);
Route::put('/disk/purposes', [DiskController::class, 'updateDiskPurposes']);
