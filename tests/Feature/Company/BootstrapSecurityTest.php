<?php

use App\Domains\Accounts\Models\User;
use App\Platform\Operations\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::findOrFail(1);
    $this->withHeaders(['company' => $user->companies()->firstOrFail()->id]);
    Sanctum::actingAs($user, ['*']);
});

test('bootstrap does not expose the retired marketplace API token', function () {
    Setting::setSetting('api_token', 'legacy-marketplace-token');

    getJson('/api/v1/bootstrap')
        ->assertOk()
        ->assertJsonMissingPath('global_settings.api_token')
        ->assertDontSee('legacy-marketplace-token');
});
