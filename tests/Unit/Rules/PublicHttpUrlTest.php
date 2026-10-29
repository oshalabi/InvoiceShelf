<?php

use App\Rules\PublicHttpUrl;
use Illuminate\Support\Facades\Validator;

test('passes for public URLs', function (string $url) {
    $validator = Validator::make(
        ['base_url' => $url],
        ['base_url' => [new PublicHttpUrl]]
    );

    expect($validator->passes())->toBeTrue();
})->with([
    'https literal' => 'https://1.1.1.1/api/v1',
    'unresolvable host fails open' => 'https://surely-not-a-real-host.invalid/api',
]);

test('fails for private or reserved URLs', function (string $url) {
    $validator = Validator::make(
        ['base_url' => $url],
        ['base_url' => [new PublicHttpUrl]]
    );

    expect($validator->passes())->toBeFalse();
})->with([
    'cloud metadata' => 'http://169.254.169.254/latest/meta-data/',
    'loopback' => 'http://127.0.0.1:11434',
    'rfc1918' => 'http://10.0.0.5',
    'localhost' => 'http://localhost',
]);

test('passes for empty values so it composes with nullable', function () {
    $validator = Validator::make(
        ['base_url' => ''],
        ['base_url' => ['nullable', new PublicHttpUrl]]
    );

    expect($validator->passes())->toBeTrue();
});
