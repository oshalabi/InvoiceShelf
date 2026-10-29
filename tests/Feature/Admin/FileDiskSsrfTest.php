<?php

use App\Domains\Accounts\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

/**
 * S3 / DigitalOcean Spaces endpoints are admin-supplied and fetched server-side
 * during credential validation, so a private/reserved endpoint must be rejected.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->withHeaders(['company' => $user->companies()->first()->id]);
    Sanctum::actingAs($user, ['*']);
});

test('rejects a doSpaces disk whose endpoint targets a private host', function () {
    postJson('/api/v1/disks', [
        'name' => 'evil-spaces',
        'driver' => 'doSpaces',
        'credentials' => [
            'key' => 'k',
            'secret' => 's',
            'region' => 'nyc3',
            'bucket' => 'b',
            'endpoint' => 'http://169.254.169.254',
            'root' => '/',
        ],
        'set_as_default' => false,
    ])->assertStatus(422)->assertJsonValidationErrors('credentials.endpoint');
});

test('rejects an s3 disk whose endpoint targets a private host', function () {
    postJson('/api/v1/disks', [
        'name' => 'evil-s3',
        'driver' => 's3',
        'credentials' => [
            'key' => 'k',
            'secret' => 's',
            'region' => 'us-east-1',
            'bucket' => 'b',
            'endpoint' => 'http://10.1.2.3',
            'root' => '/',
        ],
        'set_as_default' => false,
    ])->assertStatus(422)->assertJsonValidationErrors('credentials.endpoint');
});
