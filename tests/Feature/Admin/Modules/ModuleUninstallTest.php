<?php

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use App\Platform\Modules\Events\ModuleUninstalledEvent;
use App\Platform\Modules\Http\Resources\ModuleResource;
use App\Platform\Modules\Models\MarketplaceOperation;
use App\Platform\Modules\Models\Module;
use App\Platform\Modules\Runtime\ModuleRuntimeAutoloader;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Nwidart\Modules\Facades\Module as RuntimeModule;

use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);

    $user = User::findOrFail(1);
    $this->withHeader('company', $user->companies()->firstOrFail()->id);
    Sanctum::actingAs($user, ['*']);
});

afterEach(function () {
    File::deleteDirectory(base_path('Modules/UninstallDependent'));
    File::deleteDirectory(base_path('Modules/UninstallRuntime'));
    File::deleteDirectory(base_path('Modules/.backups'));
    Schema::dropIfExists('uninstall_runtime_trace');
});

it('removes a real runtime while preserving code-only uninstall data and migration rows', function () {
    $module = runtimeFixture(schemaVersion: 1);
    $migration = '2026_08_05_000001_uninstall_runtime_first';
    DB::table('migrations')->insert(['migration' => $migration, 'batch' => 1]);
    DB::table('company_settings')->insert([
        'company_id' => User::findOrFail(1)->companies()->firstOrFail()->id,
        'option' => 'module.uninstall-runtime.enabled',
        'value' => '1',
    ]);

    postJson("/api/v1/modules/{$module->name}/uninstall", ['remove_data' => false])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(base_path('Modules/UninstallRuntime'))->not->toBeDirectory()
        ->and(DB::table('migrations')->where('migration', $migration)->exists())->toBeTrue()
        ->and(DB::table('company_settings')->where('option', 'module.uninstall-runtime.enabled')->exists())->toBeTrue();
});

it('reconciles a missing runtime through a code-only uninstall', function () {
    Event::fake([ModuleUninstalledEvent::class]);
    $module = installedModuleForUninstall();

    postJson("/api/v1/modules/{$module->name}/uninstall", ['remove_data' => false])
        ->assertOk()
        ->assertJsonPath('success', true);

    $module->refresh();

    expect($module->installed)->toBeFalse()
        ->and($module->enabled)->toBeFalse()
        ->and($module->state)->toBe('uninstalled');

    Event::assertDispatched(ModuleUninstalledEvent::class);
});

it('fails destructive uninstall safely when the module runtime is missing', function () {
    $module = installedModuleForUninstall();

    postJson("/api/v1/modules/{$module->name}/uninstall", [
        'remove_data' => true,
        'confirmation' => $module->name,
    ])
        ->assertConflict()
        ->assertJsonPath('error', 'module_runtime_missing');

    $module->refresh();

    expect($module->installed)->toBeTrue()
        ->and($module->enabled)->toBeFalse()
        ->and($module->state)->toBe('failed')
        ->and($module->last_error)->toStartWith('module_runtime_missing:');
});

it('requires an exact module name before destructive uninstall', function () {
    $module = installedModuleForUninstall();

    postJson("/api/v1/modules/{$module->name}/uninstall", [
        'remove_data' => true,
        'confirmation' => 'wrong-name',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('confirmation');
});

it('blocks uninstall when an installed module depends on it', function () {
    $module = installedModuleForUninstall();
    File::ensureDirectoryExists(base_path('Modules/UninstallDependent'));
    File::put(base_path('Modules/UninstallDependent/module.json'), json_encode([
        'slug' => 'uninstall-dependent',
        'module_dependencies' => ['uninstall-probe' => '^1.0.0'],
    ], JSON_THROW_ON_ERROR));
    Module::query()->create([
        'name' => 'UninstallDependent',
        'slug' => 'uninstall-dependent',
        'version' => '1.0.0',
        'installed' => true,
        'enabled' => true,
        'state' => 'installed',
    ]);

    postJson("/api/v1/modules/{$module->name}/uninstall", ['remove_data' => false])
        ->assertConflict()
        ->assertJsonPath('error', 'dependent_modules_installed');

    $module->refresh();

    expect($module->installed)->toBeTrue()
        ->and($module->enabled)->toBeTrue()
        ->and($module->state)->toBe('installed');
});

it('does not start an uninstall while another marketplace operation holds the lease', function () {
    $module = installedModuleForUninstall();
    MarketplaceOperation::query()->create([
        'lock_name' => 'marketplace-install',
        'slug' => 'other-module',
        'version' => '1.0.0',
        'channel' => 'stable',
        'status' => 'running',
        'started_at' => now(),
        'expires_at' => now()->addMinute(),
    ]);

    postJson("/api/v1/modules/{$module->name}/uninstall", ['remove_data' => false])
        ->assertConflict()
        ->assertJsonPath('error', 'operation_in_progress');

    $module->refresh();

    expect($module->enabled)->toBeTrue()
        ->and($module->state)->toBe('installed');
});

it('exposes cleanup capability only for schema-v2 cleanup modules', function () {
    requireSdk32();
    $module = runtimeFixture(schemaVersion: 2, cleanupClass: 'Modules\\UninstallRuntime\\Providers\\UninstallRuntimeServiceProvider');
    $payload = (object) [
        'module_name' => $module->name,
        'slug' => $module->slug,
        'name' => 'Uninstall Runtime',
    ];

    $data = (new ModuleResource($payload))->toArray(request());

    expect($data['supports_data_cleanup'])->toBeTrue();
});

it('cleans data before resetting all migrations and removes all module settings', function () {
    requireSdk32();
    $module = runtimeFixture(schemaVersion: 2, cleanupClass: 'Modules\\UninstallRuntime\\Providers\\UninstallRuntimeServiceProvider');
    Schema::create('uninstall_runtime_trace', fn ($table) => $table->string('step'));
    DB::table('migrations')->insert(['migration' => '2026_08_05_000001_uninstall_runtime_first', 'batch' => 1]);
    DB::table('migrations')->insert(['migration' => '2026_08_05_000002_uninstall_runtime_second', 'batch' => 2]);
    $firstCompany = User::findOrFail(1)->companies()->firstOrFail();
    $secondCompany = Company::factory()->create();
    DB::table('company_settings')->insert([
        'company_id' => $firstCompany->id,
        'option' => 'module.uninstall-runtime.enabled',
        'value' => '1',
    ]);
    DB::table('company_settings')->insert([
        'company_id' => $secondCompany->id,
        'option' => 'module.uninstall-runtime.enabled',
        'value' => '1',
    ]);
    DB::table('company_settings')->insert([
        'company_id' => $firstCompany->id,
        'option' => 'module.unrelated-module.enabled',
        'value' => 'keep',
    ]);

    postJson("/api/v1/modules/{$module->name}/uninstall", [
        'remove_data' => true,
        'confirmation' => $module->name,
    ])->assertOk();

    expect(DB::table('uninstall_runtime_trace')->pluck('step')->all())->toBe(['cleanup', 'down:second', 'down:first'])
        ->and(DB::table('migrations')->where('migration', 'like', '%uninstall_runtime%')->exists())->toBeFalse()
        ->and(DB::table('company_settings')->where('option', 'like', 'module.uninstall-runtime.%')->exists())->toBeFalse()
        ->and(DB::table('company_settings')->where('option', 'module.unrelated-module.enabled')->value('value'))->toBe('keep')
        ->and(base_path('Modules/UninstallRuntime'))->not->toBeDirectory();
});

it('restores a failed cleanup runtime and allows a retry', function () {
    requireSdk32();
    $module = runtimeFixture(schemaVersion: 2, cleanupClass: 'Modules\\UninstallRuntime\\Providers\\UninstallRuntimeServiceProvider');
    Schema::create('uninstall_runtime_trace', fn ($table) => $table->string('step'));
    config()->set('uninstall_runtime.cleanup_throws', true);

    postJson("/api/v1/modules/{$module->name}/uninstall", [
        'remove_data' => true,
        'confirmation' => $module->name,
    ])->assertUnprocessable()->assertJsonPath('error', 'uninstall_failed');

    $module->refresh();
    expect(base_path('Modules/UninstallRuntime'))->toBeDirectory()
        ->and($module->installed)->toBeTrue()
        ->and($module->enabled)->toBeFalse()
        ->and($module->state)->toBe('failed');

    config()->set('uninstall_runtime.cleanup_throws', false);
    postJson("/api/v1/modules/{$module->name}/uninstall", [
        'remove_data' => true,
        'confirmation' => $module->name,
    ])->assertOk();
});

it('restores a failed migration reset runtime and allows a retry', function () {
    requireSdk32();
    $module = runtimeFixture(schemaVersion: 2, cleanupClass: 'Modules\\UninstallRuntime\\Providers\\UninstallRuntimeServiceProvider');
    Schema::create('uninstall_runtime_trace', fn ($table) => $table->string('step'));
    DB::table('migrations')->insert(['migration' => '2026_08_05_000001_uninstall_runtime_first', 'batch' => 1]);
    config()->set('uninstall_runtime.down_throws', true);

    postJson("/api/v1/modules/{$module->name}/uninstall", [
        'remove_data' => true,
        'confirmation' => $module->name,
    ])->assertUnprocessable()->assertJsonPath('error', 'uninstall_failed');

    $module->refresh();
    expect(base_path('Modules/UninstallRuntime'))->toBeDirectory()
        ->and($module->installed)->toBeTrue()
        ->and($module->enabled)->toBeFalse()
        ->and($module->state)->toBe('failed');

    config()->set('uninstall_runtime.down_throws', false);
    postJson("/api/v1/modules/{$module->name}/uninstall", [
        'remove_data' => true,
        'confirmation' => $module->name,
    ])->assertOk();
});

it('registers only the safe module uninstall command', function () {
    expect(Artisan::all())->toHaveKey('module:uninstall')
        ->not->toHaveKey('module:delete');
});

function installedModuleForUninstall(): Module
{
    return Module::query()->create([
        'name' => 'UninstallProbe',
        'slug' => 'uninstall-probe',
        'version' => '1.0.0',
        'installed' => true,
        'enabled' => true,
        'state' => 'installed',
    ]);
}

function runtimeFixture(int $schemaVersion, ?string $cleanupClass = null): Module
{
    $path = base_path('Modules/UninstallRuntime');
    File::ensureDirectoryExists($path.'/app/Providers');
    File::ensureDirectoryExists($path.'/database/migrations');

    $manifest = [
        'name' => 'UninstallRuntime',
        'alias' => 'uninstall_runtime',
        'description' => 'Uninstall runtime test fixture',
        'keywords' => [],
        'priority' => 0,
        'providers' => ['Modules\\UninstallRuntime\\Providers\\UninstallRuntimeServiceProvider'],
        'aliases' => [],
        'files' => [],
        'requires' => [],
        'schema_version' => $schemaVersion,
        'slug' => 'uninstall-runtime',
        'version' => '1.0.0',
        'license' => 'AGPL-3.0-only',
        'compatibility' => ['invoiceshelf' => '^3.0.0', 'module_api' => '^1.1.0', 'php' => '^8.4.0', 'extensions' => []],
        'module_dependencies' => [],
        'migration_policy' => $schemaVersion === 2 ? 'reversible' : 'forward-only',
        'dependency_policy' => 'host-provided-only',
        'assets' => [],
    ];
    if ($schemaVersion === 2) {
        $manifest['uninstall'] = ['data_cleanup' => $cleanupClass];
    }
    File::put($path.'/module.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    File::put($path.'/app/Providers/UninstallRuntimeServiceProvider.php', runtimeProviderSource($schemaVersion));
    foreach (['first', 'second'] as $step) {
        File::put($path.'/database/migrations/2026_08_05_00000'.($step === 'first' ? '1' : '2').'_uninstall_runtime_'.$step.'.php', migrationSource($step));
    }

    ModuleRuntimeAutoloader::register('UninstallRuntime');
    RuntimeModule::register();

    return Module::query()->updateOrCreate(['name' => 'UninstallRuntime'], [
        'slug' => 'uninstall-runtime',
        'version' => '1.0.0',
        'installed' => true,
        'enabled' => true,
        'state' => 'installed',
    ]);
}

function runtimeProviderSource(int $schemaVersion): string
{
    $contract = $schemaVersion === 2 ? ' implements \\InvoiceShelf\\Modules\\Contracts\\DataCleanup' : '';
    $cleanup = $schemaVersion === 2 ? <<<'PHP'

    public function cleanup(): void
    {
        if (config('uninstall_runtime.cleanup_throws')) {
            throw new RuntimeException('cleanup failed');
        }

        DB::table('uninstall_runtime_trace')->insert(['step' => 'cleanup']);
    }
PHP : '';

    return "<?php

namespace Modules\\UninstallRuntime\\Providers;

use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\ServiceProvider;
use RuntimeException;

class UninstallRuntimeServiceProvider extends ServiceProvider{$contract}
{{$cleanup}
}
";
}

function migrationSource(string $step): string
{
    return "<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Support\\Facades\\DB;

return new class extends Migration
{
    public function up(): void {}

    public function down(): void
    {
        if (config('uninstall_runtime.down_throws')) {
            throw new RuntimeException('migration reset failed');
        }

        DB::table('uninstall_runtime_trace')->insert(['step' => 'down:{$step}']);
    }
};
";
}

function requireSdk32(): void
{
    if (! interface_exists('InvoiceShelf\\Modules\\Contracts\\DataCleanup')) {
        test()->markTestSkipped('Requires invoiceshelf/modules ^3.2.');
    }
}
