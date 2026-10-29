<?php

// Pilot behavioural suite — the domain step and environment-file editing rules.
// Spec: platform-operations-spec.md §3 (domain step, environment-file editing).
// These tests edit the real environment file and restore it afterwards.

use function Pest\Laravel\putJson;

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

it('requires a domain', function () {
    putJson('/api/v1/installation/set-domain', [])->assertStatus(422);
});

it('always writes the session domain as the host portion of the submitted value', function () {
    config(['app.url' => 'http://pilot.test']);

    putJson('/api/v1/installation/set-domain', ['app_domain' => 'http://other.test'])
        ->assertOk()
        ->assertJson(['success' => true]);

    $env = file_get_contents(base_path('.env'));
    expect($env)->toContain('SESSION_DOMAIN=other.test');
});

it('writes the stateful-domains entry when the submitted domain differs from the current one', function () {
    config(['app.url' => 'http://pilot.test']);

    putJson('/api/v1/installation/set-domain', ['app_domain' => 'elsewhere.test'])->assertOk();

    $env = file_get_contents(base_path('.env'));
    expect($env)->toContain('SANCTUM_STATEFUL_DOMAINS=elsewhere.test');
    expect($env)->toContain('SESSION_DOMAIN=elsewhere.test');
});

it('replaces an existing key in place rather than appending a duplicate', function () {
    config(['app.url' => 'http://pilot.test']);

    putJson('/api/v1/installation/set-domain', ['app_domain' => 'first.test'])->assertOk();
    putJson('/api/v1/installation/set-domain', ['app_domain' => 'second.test'])->assertOk();

    $env = file_get_contents(base_path('.env'));
    expect(substr_count($env, "\nSESSION_DOMAIN=") + (str_starts_with($env, 'SESSION_DOMAIN=') ? 1 : 0))
        ->toBe(1);
    expect($env)->toContain('SESSION_DOMAIN=second.test');
});
