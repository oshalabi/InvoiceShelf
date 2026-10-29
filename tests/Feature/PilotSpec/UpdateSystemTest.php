<?php

// Pilot behavioural suite — self-update pipeline, version endpoint, console refusal.
// Spec: platform-operations-spec.md §4.
// The manifest-based clean and the copy-over-installation-root steps are
// sandbox-only scenarios (see README); everything here is safe on a working tree.

use App\Domains\Accounts\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

$pilotReleaseServer = null;

function pilotStartReleaseServer(): void
{
    global $pilotReleaseServer;
    if ($pilotReleaseServer) {
        return;
    }
    $router = __DIR__.'/fixtures/release-server-router.php';
    $pilotReleaseServer = proc_open(
        [PHP_BINARY, '-S', '127.0.0.1:8873', $router],
        [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
        $pipes
    );
    for ($i = 0; $i < 50; $i++) {
        if (@file_get_contents('http://127.0.0.1:8873/releases/update-check/0') !== false) {
            return;
        }
        usleep(100_000);
    }
}

afterAll(function () {
    global $pilotReleaseServer;
    if ($pilotReleaseServer) {
        proc_terminate($pilotReleaseServer);
        $pilotReleaseServer = null;
    }
});

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::where('role', 'super admin')->first();
    $this->withHeaders(['company' => $user->companies()->first()->id]);
    Sanctum::actingAs($user, ['*']);
});

it('rejects unauthenticated update requests', function () {
    $this->flushHeaders();
    app('auth')->forgetGuards();

    getJson('/api/v1/check/update')->assertUnauthorized();
    postJson('/api/v1/update/finish', ['installed' => 'a', 'version' => 'b'])->assertUnauthorized();
});

it('reads the installed version and the self-healing channel from the version endpoint', function () {
    expect(DB::table('settings')->where('option', 'updater_channel')->exists())->toBeFalse();

    $expected = preg_replace('~[\r\n]+~', '', file_get_contents(base_path('version.md')));

    getJson('/api/v1/app/version')
        ->assertOk()
        ->assertJson(['version' => $expected, 'channel' => 'stable']);

    // The default channel is persisted on first read.
    expect(DB::table('settings')->where('option', 'updater_channel')->value('value'))->toBe('stable');
});

it('checks for updates against the release server and grades required extensions', function () {
    pilotStartReleaseServer();
    config(['invoiceshelf.base_url' => 'http://127.0.0.1:8873']);

    $payload = getJson('/api/v1/check/update?channel=stable')->assertOk()->json();

    expect($payload['success'])->toBeTrue();
    $extensions = $payload['release']['extensions'];
    expect($extensions['curl'])->toBeTrue();
    expect($extensions['pilot_missing_ext'])->toBeFalse();
    expect($extensions['php(8.0)'])->toBeTrue();
});

it('downloads a release archive into private storage', function () {
    pilotStartReleaseServer();
    config(['invoiceshelf.base_url' => 'http://127.0.0.1:8873']);

    $payload = postJson('/api/v1/update/download', ['version' => '9.9.9-test'])->assertOk()->json();

    expect($payload['success'])->toBeTrue();
    expect($payload['path'])->toBeString()->toEndWith('.zip');
    expect(str_starts_with($payload['path'], storage_path('app')))->toBeTrue();
    expect(file_exists($payload['path']))->toBeTrue();

    File::deleteDirectory(dirname($payload['path']));
});

it('unzips a release archive and deletes the archive file', function () {
    $zipPath = storage_path('framework/testing/pilot-release.zip');
    @mkdir(dirname($zipPath), 0775, true);
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('InvoiceShelf/pilot-marker.txt', 'ok');
    $zip->close();

    $payload = postJson('/api/v1/update/unzip', ['path' => $zipPath])->assertOk()->json();

    expect($payload['success'])->toBeTrue();
    expect(file_exists($payload['path'].'/InvoiceShelf/pilot-marker.txt'))->toBeTrue();
    expect(file_exists($zipPath))->toBeFalse();

    File::deleteDirectory($payload['path']);
});

it('fails the unzip step with an error when the archive is missing', function () {
    postJson('/api/v1/update/unzip', ['path' => storage_path('framework/testing/absent.zip')])
        ->assertStatus(500)
        ->assertJson(['success' => false]);
});

it('deletes exactly the listed legacy files during clean-up when no manifest exists', function () {
    if (file_exists(base_path('manifest.json'))) {
        $this->markTestSkipped('A root manifest exists; the legacy branch cannot be exercised safely.');
    }

    $stale = base_path('storage/framework/testing/pilot-stale.txt');
    @mkdir(dirname($stale), 0775, true);
    file_put_contents($stale, 'stale');
    $kept = base_path('storage/framework/testing/pilot-kept.txt');
    file_put_contents($kept, 'kept');

    postJson('/api/v1/update/delete', [
        'deleted_files' => json_encode(['storage/framework/testing/pilot-stale.txt']),
    ])->assertOk()->assertJson(['success' => true]);

    expect(file_exists($stale))->toBeFalse();
    expect(file_exists($kept))->toBeTrue();

    @unlink($kept);
});

it('treats a missing manifest as nothing to clean', function () {
    if (file_exists(base_path('manifest.json'))) {
        $this->markTestSkipped('A root manifest exists; skipping the no-manifest branch.');
    }

    postJson('/api/v1/update/clean')
        ->assertOk()
        ->assertJson(['success' => true, 'cleaned' => 0]);
});

it('runs pending migrations as an update step', function () {
    postJson('/api/v1/update/migrate')->assertOk()->assertJson(['success' => true]);
});

it('stamps the new version when finishing an update', function () {
    postJson('/api/v1/update/finish', ['installed' => '3.0.0', 'version' => '9.9.9-test'])
        ->assertOk()
        ->assertJson(['success' => true, 'error' => false]);

    expect(DB::table('settings')->where('option', 'version')->value('value'))->toBe('9.9.9-test');
});

it('validates the finish payload', function () {
    postJson('/api/v1/update/finish', [])->assertStatus(422)
        ->assertJsonValidationErrors(['installed', 'version']);
});

it('refuses the console updater in containerized installs', function () {
    config(['invoiceshelf.containerized' => true]);

    $this->artisan('core:update')
        ->expectsOutputToContain('disabled in containerized installs')
        ->assertExitCode(0);
});
