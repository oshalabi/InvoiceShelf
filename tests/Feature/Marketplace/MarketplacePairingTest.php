<?php

use App\Domains\Accounts\Models\User;
use App\Platform\Modules\Models\MarketplaceCredential;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    Artisan::call('db:seed', ['--force' => true, '--class' => 'DatabaseSeeder']);
    Artisan::call('db:seed', ['--force' => true, '--class' => 'DemoSeeder']);
    Sanctum::actingAs(User::findOrFail(1), ['*']);
    config()->set('invoiceshelf.base_url', 'https://marketplace.test');
});

it('starts pairing with installation compatibility metadata', function () {
    Http::fake([
        'https://marketplace.test/api/marketplace/v1/device/code' => Http::response([
            'success' => true, 'device_code' => 'device-code', 'user_code' => 'ABCD1234',
            'verification_uri' => 'https://marketplace.test/pair', 'expires_in' => 600, 'interval' => 5,
        ], 201),
    ]);

    postJson('/api/v1/modules/pairing/start')
        ->assertCreated()
        ->assertJsonPath('user_code', 'ABCD1234');

    Http::assertSent(function ($request): bool {
        $data = $request->data();

        return $request->url() === 'https://marketplace.test/api/marketplace/v1/device/code'
            && filled($data['installation_name'] ?? null)
            && isset($data['module_api_version'], $data['php_version'], $data['extensions'])
            && collect($data['extensions'])->every(
                fn ($extension): bool => is_string($extension)
                    && preg_match('/^ext-[a-z0-9][a-z0-9_-]*$/', $extension) === 1,
            );
    });
});

it('stores only the encrypted opaque installation token after pairing', function () {
    Http::fake([
        'https://marketplace.test/api/marketplace/v1/device/code' => Http::response([
            'success' => true, 'device_code' => 'device-code', 'user_code' => 'ABCD1234',
            'verification_uri' => 'https://marketplace.test/pair', 'expires_in' => 600, 'interval' => 5,
        ], 201),
        'https://marketplace.test/api/marketplace/v1/device/token' => Http::response([
            'success' => true, 'installation_token' => 'opaque-installation-token', 'installation' => ['id' => 17, 'name' => 'Local'],
        ]),
    ]);

    postJson('/api/v1/modules/pairing/start')->assertCreated();
    postJson('/api/v1/modules/pairing/poll')->assertOk()->assertJsonPath('status', 'paired');

    $credential = MarketplaceCredential::query()->sole();
    expect($credential->credential)->not->toContain('opaque-installation-token')
        ->and(Crypt::decryptString($credential->credential))->toBe('opaque-installation-token')
        ->and($credential->device_id)->toBe('17');
});

it('reports pending device approval without storing a credential', function () {
    Http::fake([
        'https://marketplace.test/api/marketplace/v1/device/code' => Http::response([
            'success' => true, 'device_code' => 'device-code', 'user_code' => 'ABCD1234',
            'verification_uri' => 'https://marketplace.test/pair', 'expires_in' => 600, 'interval' => 5,
        ], 201),
        'https://marketplace.test/api/marketplace/v1/device/token' => Http::response([
            'success' => false, 'error' => 'authorization_pending', 'interval' => 5,
        ], 428),
    ]);

    postJson('/api/v1/modules/pairing/start')->assertCreated();
    postJson('/api/v1/modules/pairing/poll')->assertOk()->assertJsonPath('status', 'pending');

    expect(MarketplaceCredential::query()->doesntExist())->toBeTrue();
});

it('revokes the remote installation when disconnecting locally', function () {
    MarketplaceCredential::query()->create([
        'credential' => Crypt::encryptString('opaque-installation-token'),
        'paired_at' => now(),
    ]);
    Http::fake([
        'https://marketplace.test/api/marketplace/v1/device' => Http::response(['success' => true]),
    ]);

    deleteJson('/api/v1/modules/pairing')->assertOk()->assertJsonPath('success', true);

    expect(MarketplaceCredential::query()->exists())->toBeFalse();
    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && $request->url() === 'https://marketplace.test/api/marketplace/v1/device'
        && $request->hasHeader('Authorization', 'Bearer opaque-installation-token'));
});
