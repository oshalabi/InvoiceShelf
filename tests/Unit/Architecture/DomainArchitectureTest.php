<?php

/**
 * @return list<string>
 */
function phpFilesUnder(string $directory): array
{
    if (! is_dir($directory)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

test('domain code does not depend on legacy service or controller layers', function () {
    $files = phpFilesUnder(app_path('Domains'));

    expect($files)->toBeArray();

    foreach ($files as $file) {
        $source = file_get_contents($file);

        expect($source)->not->toContain('App\\Http\\Controllers')
            ->not->toContain('App\\Services');
    }
});

test('platform code does not depend on legacy controllers', function () {
    foreach (phpFilesUnder(app_path('Platform')) as $file) {
        expect(file_get_contents($file))->not->toContain('App\\Http\\Controllers');
    }
});

test('legacy application layer directories contain no php classes', function () {
    $legacyDirectories = [
        'Console/Commands',
        'Http/Controllers',
        'Http/Requests',
        'Http/Resources',
        'Jobs',
        'Mail',
        'Models',
        'Policies',
        'Services',
        'Traits',
    ];

    foreach ($legacyDirectories as $directory) {
        expect(phpFilesUnder(app_path($directory)))->toBe([]);
    }
});

test('support remains independent of domains', function () {
    foreach (phpFilesUnder(app_path('Support')) as $file) {
        expect(file_get_contents($file))->not->toContain('App\\Domains');
    }
});
