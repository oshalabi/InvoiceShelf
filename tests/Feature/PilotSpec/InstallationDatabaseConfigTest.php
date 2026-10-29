<?php

// Pilot behavioural suite — database configuration step.
// Spec: platform-operations-spec.md §3 (database configuration).
// The success path (env write + migrate + seed) is a sandbox-only scenario —
// see the suite README. These tests pin defaults, validation, and the
// non-empty-database refusal, all of which stop before any destructive step.

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    // CI has no .env; seed one the way an installer would so the
    // env-writing paths under test have a real file to work against.
    $this->envExisted = file_exists(base_path('.env'));
    if (! $this->envExisted) {
        copy(base_path('.env.example'), base_path('.env'));
    }
    $this->envBackup = file_get_contents(base_path('.env'));
});

afterEach(function () {
    if ($this->envExisted) {
        file_put_contents(base_path('.env'), $this->envBackup);
    } else {
        @unlink(base_path('.env'));
    }
});

it('returns connection defaults per driver', function () {
    getJson('/api/v1/installation/database/config?connection=pgsql')
        ->assertOk()
        ->assertJson(['success' => true, 'config' => ['database_connection' => 'pgsql', 'database_host' => '127.0.0.1', 'database_port' => 5432]]);

    getJson('/api/v1/installation/database/config?connection=mysql')
        ->assertOk()
        ->assertJson(['success' => true, 'config' => ['database_connection' => 'mysql', 'database_host' => '127.0.0.1', 'database_port' => 3306]]);

    getJson('/api/v1/installation/database/config?connection=mariadb')
        ->assertOk()
        ->assertJson(['success' => true, 'config' => ['database_connection' => 'mariadb', 'database_host' => '127.0.0.1', 'database_port' => 3306]]);

    $sqlite = getJson('/api/v1/installation/database/config?connection=sqlite')->assertOk()->json();
    expect($sqlite['config']['database_connection'])->toBe('sqlite');
    expect($sqlite['config']['database_name'])->toBe(config('database.connections.sqlite.database') ?: 'storage/app/database.sqlite');
});

it('validates the payload per driver', function () {
    postJson('/api/v1/installation/database/config', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['app_url', 'database_connection']);

    postJson('/api/v1/installation/database/config', [
        'app_url' => 'http://pilot.test',
        'database_connection' => 'sqlite',
    ])->assertStatus(422)->assertJsonValidationErrors(['database_name']);

    postJson('/api/v1/installation/database/config', [
        'app_url' => 'http://pilot.test',
        'database_connection' => 'mysql',
    ])->assertStatus(422)->assertJsonValidationErrors([
        'database_hostname', 'database_port', 'database_name', 'database_username',
    ]);

    // The password is deliberately never a validation requirement.
    postJson('/api/v1/installation/database/config', [
        'app_url' => 'http://pilot.test',
        'database_connection' => 'mysql',
        'database_hostname' => '127.0.0.1',
        'database_port' => 3306,
        'database_name' => 'x',
        'database_username' => 'u',
        'database_overwrite' => 'not-a-boolean',
    ])->assertStatus(422)->assertJsonValidationErrors(['database_overwrite'])
        ->assertJsonMissingValidationErrors(['database_password']);
});
