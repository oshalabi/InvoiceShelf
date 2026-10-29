<?php

use App\Platform\Operations\Update\Updater;

/*
 * The cleanup sweep's keep-list. The static config prefixes are the baseline;
 * on top of them the live SQLite database file (and its side files) must be
 * protected wherever the installer put it, because user state can never appear
 * in a release manifest and would otherwise be swept as stale.
 */

test('the configured protected prefixes are always kept', function (): void {
    $paths = Updater::protectedPaths();

    foreach (config('invoiceshelf.update_protected_paths') as $prefix) {
        expect($paths)->toContain($prefix);
    }
});

test('a sqlite database inside the installation is protected with its side files', function (): void {
    config()->set('database.connections.sqlite.database', base_path('database/database.sqlite'));

    $paths = Updater::protectedPaths();

    expect($paths)
        ->toContain('database/database.sqlite')
        ->toContain('database/database.sqlite-wal')
        ->toContain('database/database.sqlite-shm')
        ->toContain('database/database.sqlite-journal');
});

test('a relative sqlite path resolves against the installation root', function (): void {
    config()->set('database.connections.sqlite.database', 'database/relative.sqlite');

    expect(Updater::protectedPaths())->toContain('database/relative.sqlite');
});

test('an in-memory database adds nothing to the keep list', function (): void {
    config()->set('database.connections.sqlite.database', ':memory:');

    $baseline = count(config('invoiceshelf.update_protected_paths'));

    expect(Updater::protectedPaths())->toHaveCount($baseline);
});

test('a sqlite database outside the installation adds nothing to the keep list', function (): void {
    config()->set('database.connections.sqlite.database', '/var/lib/invoiceshelf/external.sqlite');

    $baseline = count(config('invoiceshelf.update_protected_paths'));

    expect(Updater::protectedPaths())->toHaveCount($baseline);
});
