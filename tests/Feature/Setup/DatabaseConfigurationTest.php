<?php

use function Pest\Laravel\getJson;

/**
 * The setup wizard picks which form to render from `database_connection` in this
 * response. When the switch had no arm for the requested driver it returned an
 * empty config, so step 4 rendered blank with no way to continue and nothing in
 * the log — the app never errored, it just answered with nothing.
 *
 * That is what InvoiceShelf/docker#79 hit: the shipped docker-compose.mysql.yml
 * sets DB_CONNECTION=mariadb, so a fresh install using the official compose file
 * could not get past the database step.
 */
test('the database config endpoint answers with a usable config for every supported driver', function (string $connection) {
    $response = getJson("/api/v1/installation/database/config?connection={$connection}");

    $response->assertOk();

    expect($response->json('config.database_connection'))->toBe($connection);
})->with([
    'mysql',
    'mariadb',
    'pgsql',
    'sqlite',
]);

test('sqlite is told where its database file lives', function () {
    $response = getJson('/api/v1/installation/database/config?connection=sqlite');

    $response->assertOk();

    expect($response->json('config.database_name'))->not->toBeEmpty();
});

test('server-based drivers are given a host and port to prefill', function (string $connection) {
    $response = getJson("/api/v1/installation/database/config?connection={$connection}");

    expect($response->json('config.database_host'))->not->toBeEmpty()
        ->and($response->json('config.database_port'))->not->toBeEmpty();
})->with([
    'mysql',
    'mariadb',
    'pgsql',
]);

/**
 * A driver we do not recognise must still produce something the wizard can
 * render, rather than the empty config that caused #79.
 */
test('an unrecognised driver still returns a renderable config', function () {
    $response = getJson('/api/v1/installation/database/config?connection=cockroach');

    $response->assertOk();

    expect($response->json('config.database_connection'))->toBe('cockroach')
        ->and($response->json('config'))->not->toBeEmpty();
});
