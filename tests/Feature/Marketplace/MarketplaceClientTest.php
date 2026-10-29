<?php

use App\Platform\Modules\Marketplace\MarketplaceClient;
use Illuminate\Support\Facades\Http;

it('downloads HTTP artifacts from the configured local marketplace origin', function () {
    config()->set('invoiceshelf.base_url', 'http://marketplace.test:8080');
    $destination = tempnam(sys_get_temp_dir(), 'marketplace-artifact-');

    Http::fake([
        'http://marketplace.test:8080/artifacts/secure-probe.zip' => Http::response('archive'),
    ]);

    try {
        $response = app(MarketplaceClient::class)->artifact(
            'http://marketplace.test:8080/artifacts/secure-probe.zip',
            $destination,
        );

        expect($response->successful())->toBeTrue();
        Http::assertSent(fn ($request): bool => $request->url() === 'http://marketplace.test:8080/artifacts/secure-probe.zip');
    } finally {
        unlink($destination);
    }
});

it('rejects HTTP artifacts from unrelated marketplace origins', function (string $url) {
    config()->set('invoiceshelf.base_url', 'http://marketplace.test:8080');
    $destination = tempnam(sys_get_temp_dir(), 'marketplace-artifact-');

    try {
        expect(fn () => app(MarketplaceClient::class)->artifact($url, $destination))
            ->toThrow(RuntimeException::class, 'unsafe artifact URL');
        Http::assertNothingSent();
    } finally {
        unlink($destination);
    }
})->with([
    'different host' => 'http://artifacts.test:8080/secure-probe.zip',
    'different port' => 'http://marketplace.test/secure-probe.zip',
]);
