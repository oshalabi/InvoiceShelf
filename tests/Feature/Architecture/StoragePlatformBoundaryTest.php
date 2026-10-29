<?php

use App\Platform\Storage\Application\FileDiskService;
use App\Platform\Storage\Contracts\StorageConfigurator;
use App\Platform\Storage\Http\BackupsController;
use App\Platform\Storage\Http\DiskController;
use App\Platform\Storage\StorageServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

test('the storage platform owns its provider extensions and authorization', function () {
    expect(app()->getProviders(StorageServiceProvider::class))->toHaveCount(1)
        ->and(app(StorageConfigurator::class))->toBeInstanceOf(FileDiskService::class)
        ->and(Gate::has('manage backups'))->toBeTrue()
        ->and(Gate::has('manage file disk'))->toBeTrue()
        ->and(Artisan::all())->toHaveKey('media:secure');

    expect(class_exists('App\\Providers\\DropboxServiceProvider'))->toBeFalse()
        ->and(class_exists('App\\Services\\Storage\\FileDiskService'))->toBeFalse()
        ->and(class_exists('App\\Jobs\\CreateBackupJob'))->toBeFalse()
        ->and(class_exists('App\\Http\\Controllers\\Admin\\BackupsController'))->toBeFalse()
        ->and(class_exists('App\\Http\\Controllers\\Admin\\Settings\\DiskController'))->toBeFalse();
});

test('the storage platform preserves its public routes and middleware', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => preg_match(
            '#^api/v1/(backups(?:/|$)|disks(?:/|$)|download-backup$|disk/(?:drivers|purposes)$)#',
            $route->uri(),
        ) === 1)
        ->keyBy(fn ($route): string => implode('|', $route->methods()).' '.$route->uri());

    expect($routes->keys()->sort()->values()->all())->toBe(collect([
        'DELETE api/v1/backups/{backup}',
        'DELETE api/v1/disks/{disk}',
        'GET|HEAD api/v1/backups',
        'GET|HEAD api/v1/backups/{backup}',
        'GET|HEAD api/v1/disk/drivers',
        'GET|HEAD api/v1/disk/purposes',
        'GET|HEAD api/v1/disks',
        'GET|HEAD api/v1/disks/{disk}',
        'GET|HEAD api/v1/download-backup',
        'POST api/v1/backups',
        'POST api/v1/disks',
        'PUT api/v1/disk/purposes',
        'PUT|PATCH api/v1/backups/{backup}',
        'PUT|PATCH api/v1/disks/{disk}',
    ])->sort()->values()->all());

    foreach ($routes as $route) {
        expect($route->getActionName())->toStartWith('App\\Platform\\Storage\\Http\\')
            ->and($route->gatherMiddleware())->toContain('auth:sanctum', 'company', 'bouncer');
    }

    expect($routes->get('GET|HEAD api/v1/backups')->getActionName())
        ->toBe(BackupsController::class.'@index')
        ->and($routes->get('GET|HEAD api/v1/disks')->getActionName())
        ->toBe(DiskController::class.'@index');
});
