<?php

// Pilot behavioural suite — the non-empty-database refusal.
// Kept in its own file, alphabetically last: exercising the refusal swaps the
// application's active database connection, and the swap has to be undone by
// hand afterwards or every file running after it in the same process inherits
// a database it cannot migrate.

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\postJson;

// Swaps the process's DB connection — excluded from default runs (phpunit.xml),
// executed standalone as the last step of each verification pass.
uses()->group('isolated', 'serial-only');

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    // CI has no .env; seed one the way an installer would so the
    // env-writing paths under test have a real file to work against.
    $this->envExisted = file_exists(base_path('.env'));
    if (! $this->envExisted) {
        copy(base_path('.env.example'), base_path('.env'));
    }
    $this->envBackup = file_get_contents(base_path('.env'));

    // The wizard rewrites `database` wholesale rather than editing it, so the
    // suite's own copy has to be kept to put back afterwards.
    $this->databaseConfig = config('database');
    $this->appUrl = config('app.url');
});

afterEach(function () {
    if ($this->envExisted) {
        file_put_contents(base_path('.env'), $this->envBackup);
    } else {
        @unlink(base_path('.env'));
    }

    // Undo the connection swap.
    //
    // The wizard narrows the runtime configuration down to the submitted
    // connection and purges it (EnvironmentManager::openSubmittedConnection).
    // The purge throws away the Connection object holding the suite's
    // in-memory PDO — the object RefreshDatabase opened its transaction on and
    // relies on to roll that transaction back at teardown. Once it is gone the
    // transaction has no owner: it stays open on a PDO the framework will hand
    // to the next test, whose migrate:fresh then dies on "cannot VACUUM from
    // within a transaction", taking every file after it down with it.
    //
    // So: put the configuration back, drop whatever the wizard opened, and
    // close the orphaned transaction. RefreshDatabase sees an untransacted
    // connection at teardown and re-migrates for the next test, which is
    // exactly the recovery it does that check for.
    config(['database' => $this->databaseConfig, 'app.url' => $this->appUrl]);

    $name = config('database.default');

    DB::purge($name);

    $pdo = RefreshDatabaseState::$inMemoryConnections[$name] ?? null;

    if ($pdo !== null && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
});

it('refuses a database that already contains data, without touching the environment file', function () {
    $path = storage_path('framework/testing/pilot-nonempty.sqlite');
    @mkdir(dirname($path), 0775, true);
    @unlink($path);
    $db = new SQLite3($path);
    $db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
    $db->close();

    $response = postJson('/api/v1/installation/database/config', [
        'app_url' => 'http://pilot.test',
        'database_connection' => 'sqlite',
        'database_name' => $path,
    ])->assertOk()->json();

    expect($response['error'])->toBe('database_should_be_empty');
    expect(file_get_contents(base_path('.env')))->toBe($this->envBackup);

    @unlink($path);
});
