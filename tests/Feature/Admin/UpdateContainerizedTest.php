<?php

use App\Domains\Accounts\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::where('role', 'super admin')->first();

    $this->withHeaders([
        'company' => $user->companies()->first()->id,
    ]);

    Sanctum::actingAs($user, ['*']);
});

it('exposes the containerized flag from the app version endpoint', function () {
    config(['invoiceshelf.containerized' => true]);

    getJson('/api/v1/app/version')
        ->assertOk()
        ->assertJson(['containerized' => true]);

    config(['invoiceshelf.containerized' => false]);

    getJson('/api/v1/app/version')
        ->assertOk()
        ->assertJson(['containerized' => false]);
});

it('blocks the in-app updater endpoints when containerized', function () {
    config(['invoiceshelf.containerized' => true]);

    getJson('/api/v1/check/update')->assertForbidden();
    postJson('/api/v1/update/download', ['version' => '2.4.0'])->assertForbidden();
    postJson('/api/v1/update/unzip', ['path' => '/tmp/x.zip'])->assertForbidden();
    postJson('/api/v1/update/copy', ['path' => '/tmp/x'])->assertForbidden();
    postJson('/api/v1/update/clean')->assertForbidden();
    postJson('/api/v1/update/migrate')->assertForbidden();
    postJson('/api/v1/update/finish', ['installed' => '2.4.0', 'version' => '2.4.1'])->assertForbidden();
});
