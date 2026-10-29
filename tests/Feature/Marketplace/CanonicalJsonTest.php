<?php

use App\Platform\Modules\Marketplace\CanonicalJson;

it('canonicalizes signed manifests recursively without changing list order', function () {
    $manifest = [
        'version' => '1.0.0',
        'compatibility' => ['php' => '8.4', 'extensions' => ['zip', 'sodium']],
        'artifact' => ['bytes' => 10.0, 'sha256' => 'abc'],
    ];

    expect(CanonicalJson::encode($manifest))->toBe(
        '{"artifact":{"bytes":10.0,"sha256":"abc"},"compatibility":{"extensions":["zip","sodium"],"php":"8.4"},"version":"1.0.0"}',
    );
});
