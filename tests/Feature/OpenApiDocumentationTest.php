<?php

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;

// Generating the full ~180-route document via static inference is memory-heavy;
// the test runner's default 128M limit is not enough (the CLI export runs with a
// higher CLI limit). Raise it for this file only.
ini_set('memory_limit', '1024M');

/**
 * Build the OpenAPI document in-process (the same call `scramble:export` makes).
 * Memoized so the ~180-route inference only runs once across this file's tests —
 * the document is deterministic (derived from routes/models, not seeded data).
 */
function openApiDocument(): array
{
    static $document;

    return $document ??= app(Generator::class)(Scramble::getGeneratorConfig('default'));
}

/** @return list<string> "method path" for every operation carrying a `company` header */
function companyHeaderOperations(array $document): array
{
    $matches = [];

    foreach ($document['paths'] as $path => $operations) {
        foreach ($operations as $method => $operation) {
            if (! is_array($operation)) {
                continue;
            }

            foreach ($operation['parameters'] ?? [] as $parameter) {
                if (($parameter['name'] ?? null) === 'company' && ($parameter['in'] ?? null) === 'header') {
                    $matches[] = "{$method} {$path}";
                }
            }
        }
    }

    return $matches;
}

it('generates a valid OpenAPI document for the v1 API', function () {
    $document = openApiDocument();

    expect($document['openapi'] ?? '')->toStartWith('3.')
        ->and($document['info']['title'])->toBe('InvoiceShelf API')
        ->and($document['info']['version'])->toBe(trim((string) file_get_contents(base_path('version.md'))))
        ->and($document['paths'])->not->toBeEmpty();
});

it('advertises bearer token authentication', function () {
    $document = openApiDocument();

    $scheme = collect($document['components']['securitySchemes'] ?? [])
        ->firstWhere('scheme', 'bearer');

    expect($scheme)->not->toBeNull()
        ->and($scheme['type'])->toBe('http');
});

it('adds the company header to company-scoped routes', function () {
    expect(companyHeaderOperations(openApiDocument()))->not->toBeEmpty();
});

it('does not add the company header to unauthenticated bootstrap routes', function () {
    $document = openApiDocument();

    // /auth/login runs before any tenant is resolved, so it must not require the header.
    $loginOperations = $document['paths']['/auth/login'] ?? [];

    $hasCompanyHeader = false;
    foreach ($loginOperations as $operation) {
        if (! is_array($operation)) {
            continue;
        }
        foreach ($operation['parameters'] ?? [] as $parameter) {
            if (($parameter['name'] ?? null) === 'company') {
                $hasCompanyHeader = true;
            }
        }
    }

    expect($hasCompanyHeader)->toBeFalse();
});
