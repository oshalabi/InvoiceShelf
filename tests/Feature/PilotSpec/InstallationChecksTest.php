<?php

// Pilot behavioural suite — requirements and permissions checks.
// Spec: platform-operations-spec.md §3.

use function Pest\Laravel\getJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
});

it('reports the PHP version block against the configured minimum', function () {
    $payload = getJson('/api/v1/installation/requirements')->assertOk()->json();

    $php = $payload['phpSupportInfo'];
    expect($php['minimum'])->toBe(config('installer.core.minPhpVersion'));
    expect($php['full'])->toBe(PHP_VERSION);
    expect($php['current'])->toMatch('/^\d+(\.\d+)*$/');
    expect($php['supported'])->toBe(version_compare($php['current'], $php['minimum']) >= 0);
});

it('checks every required PHP extension', function () {
    $payload = getJson('/api/v1/installation/requirements')->assertOk()->json();

    $checked = $payload['requirements']['requirements']['php'];
    foreach (config('installer.requirements')['php'] as $extension) {
        expect($checked)->toHaveKey($extension);
        expect($checked[$extension])->toBe(extension_loaded($extension));
    }
});

it('reports folder permissions with a consistent errors flag', function () {
    $payload = getJson('/api/v1/installation/permissions')->assertOk()->json();

    $entries = $payload['permissions']['permissions'];
    $folders = array_column($entries, 'folder');
    foreach (array_keys(config('installer.permissions')) as $folder) {
        expect($folders)->toContain($folder);
    }
    $anyFailed = collect($entries)->contains(fn ($entry) => $entry['isSet'] === false);
    expect($payload['permissions']['errors'])->toBe($anyFailed ? true : null);
});
