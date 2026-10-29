<?php

use App\Domains\Accounts\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

/**
 * The CurrencyConverter "DEDICATED" plan lets the user supply the API URL, which
 * the server then fetches — it must not be allowed to target a private host.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->withHeaders(['company' => $user->companies()->first()->id]);
    Sanctum::actingAs($user, ['*']);
});

test('rejects a currency_converter DEDICATED provider whose url is private', function () {
    postJson('/api/v1/exchange-rate-providers', [
        'driver' => 'currency_converter',
        'key' => 'test-key',
        'driver_config' => [
            'type' => 'DEDICATED',
            'url' => 'http://169.254.169.254',
        ],
        'active' => false,
    ])->assertStatus(422)->assertJsonValidationErrors('driver_config.url');
});
