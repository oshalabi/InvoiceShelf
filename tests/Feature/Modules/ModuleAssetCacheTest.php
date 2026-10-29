<?php

use App\Platform\Modules\Runtime\ModuleAssetVersion;
use Illuminate\Support\Facades\File;
use InvoiceShelf\Modules\Registry;

use function Pest\Laravel\get;

beforeEach(function () {
    Registry::flush();
    $this->assetDirectory = storage_path('app/module-asset-cache-test');
    File::ensureDirectoryExists($this->assetDirectory);
});

afterEach(function () {
    Registry::flush();
    File::deleteDirectory($this->assetDirectory);
});

test('script responses use immutable caching only for the current content version', function () {
    $path = $this->assetDirectory.'/cache-probe.js';
    File::put($path, 'export const version = "1.0.0";');
    Registry::registerScript('cache-probe', $path);
    $firstVersion = ModuleAssetVersion::forPath($path);

    $unversionedResponse = get('/modules/scripts/cache-probe')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript')
        ->assertSee('version = "1.0.0";', false);

    expect($unversionedResponse->headers->get('Cache-Control'))->toContain('no-store');

    File::put($path, 'export const version = "1.0.1";');
    $currentVersion = ModuleAssetVersion::forPath($path);

    expect($currentVersion)->not->toBe($firstVersion);

    $staleResponse = get('/modules/scripts/cache-probe?v='.$firstVersion)
        ->assertOk()
        ->assertSee('version = "1.0.1";', false);

    expect($staleResponse->headers->get('Cache-Control'))->toContain('no-store');

    $versionedResponse = get('/modules/scripts/cache-probe?v='.$currentVersion)
        ->assertOk()
        ->assertSee('version = "1.0.1";', false);

    expect($versionedResponse->headers->get('Cache-Control'))->toContain('public')
        ->toContain('max-age=31536000')
        ->toContain('immutable');
});

test('style responses use immutable caching only for the current content version', function () {
    $path = $this->assetDirectory.'/cache-probe.css';
    File::put($path, '.cache-probe { color: red; }');
    Registry::registerStyle('cache-probe', $path);
    $version = ModuleAssetVersion::forPath($path);

    $outdatedResponse = get('/modules/styles/cache-probe?v=outdated')
        ->assertOk()
        ->assertSee('.cache-probe { color: red; }', false);

    expect($outdatedResponse->headers->get('Content-Type'))->toStartWith('text/css')
        ->and($outdatedResponse->headers->get('Cache-Control'))->toContain('no-store');

    $versionedResponse = get('/modules/styles/cache-probe?v='.$version)
        ->assertOk()
        ->assertSee('.cache-probe { color: red; }', false);

    expect($versionedResponse->headers->get('Cache-Control'))->toContain('public')
        ->toContain('max-age=31536000')
        ->toContain('immutable');
});

test('the application shell content-versions local module asset URLs', function () {
    $scriptPath = $this->assetDirectory.'/layout-probe.js';
    $stylePath = $this->assetDirectory.'/layout-probe.css';
    File::put($scriptPath, 'export const version = "1.0.1";');
    File::put($stylePath, '.layout-probe { color: blue; }');
    Registry::registerScript('layout-probe', $scriptPath);
    Registry::registerStyle('layout-probe', $stylePath);

    $html = view('app')->render();
    $layout = File::get(resource_path('views/app.blade.php'));
    $hostAssetPosition = strpos($layout, "@vite('resources/scripts/main.ts')");
    $moduleStylePosition = strpos($layout, 'Registry::allStyles()');

    expect($html)->toContain('/modules/scripts/layout-probe?v='.ModuleAssetVersion::forPath($scriptPath))
        ->toContain('/modules/styles/layout-probe?v='.ModuleAssetVersion::forPath($stylePath))
        ->and($hostAssetPosition)->not->toBeFalse()
        ->and($moduleStylePosition)->toBeGreaterThan($hostAssetPosition);
});
