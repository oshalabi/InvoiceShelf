<?php

use App\Platform\Pdf\Application\PdfConfigurationService;
use App\Platform\Pdf\Contracts\PdfConfigurator;
use App\Platform\Pdf\Http\Admin\FontController;
use App\Platform\Pdf\Http\Admin\PdfConfigurationController;
use App\Platform\Pdf\Http\Middleware\PdfMiddleware;
use App\Platform\Pdf\PdfServiceProvider;
use App\Platform\Pdf\Rendering\PdfService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

test('the pdf platform owns rendering configuration fonts and authorization', function () {
    expect(app()->getProviders(PdfServiceProvider::class))->toHaveCount(1)
        ->and(app('pdf.driver'))->toBeInstanceOf(PdfService::class)
        ->and(app(PdfConfigurator::class))->toBeInstanceOf(PdfConfigurationService::class)
        ->and(Gate::has('manage pdf config'))->toBeTrue()
        ->and(Artisan::all())->toHaveKeys(['make:template', 'pdf:compare']);

    expect(class_exists('App\\Providers\\PdfServiceProvider'))->toBeFalse()
        ->and(class_exists('App\\Services\\FontService'))->toBeFalse()
        ->and(class_exists('App\\Facades\\Pdf'))->toBeFalse()
        ->and(class_exists('App\\Http\\Controllers\\Admin\\FontController'))->toBeFalse()
        ->and(class_exists('App\\Http\\Controllers\\Admin\\Settings\\PDFConfigurationController'))->toBeFalse()
        ->and(class_exists('App\\Http\\Requests\\PDFConfigurationRequest'))->toBeFalse()
        ->and(class_exists('App\\Console\\Commands\\ComparePdfDriversCommand'))->toBeFalse()
        ->and(trait_exists('App\\Traits\\GeneratesPdfTrait'))->toBeFalse()
        ->and(is_dir(app_path('Support/Pdf')))->toBeFalse();
});

test('the pdf platform preserves its public configuration routes and middleware', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => preg_match('#^api/v1/(?:fonts/|pdf/)#', $route->uri()) === 1)
        ->keyBy(fn ($route): string => implode('|', $route->methods()).' '.$route->uri());

    expect($routes->keys()->sort()->values()->all())->toBe(collect([
        'GET|HEAD api/v1/fonts/status',
        'GET|HEAD api/v1/pdf/config',
        'GET|HEAD api/v1/pdf/drivers',
        'POST api/v1/fonts/{package}/install',
        'POST api/v1/pdf/config',
    ])->sort()->values()->all());

    foreach ($routes as $route) {
        expect($route->getActionName())->toStartWith('App\\Platform\\Pdf\\Http\\Admin\\')
            ->and($route->gatherMiddleware())->toContain('auth:sanctum', 'company', 'bouncer');
    }

    expect($routes->get('GET|HEAD api/v1/fonts/status')->getActionName())
        ->toBe(FontController::class.'@status')
        ->and($routes->get('GET|HEAD api/v1/pdf/config')->getActionName())
        ->toBe(PdfConfigurationController::class.'@getEnvironment')
        ->and(app('router')->getMiddleware()['pdf-auth'] ?? null)
        ->toBe(PdfMiddleware::class);
});
