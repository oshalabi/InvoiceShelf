<?php

namespace App\Platform\Operations\Installation\Http\Controllers;

use App\Platform\Http\Controller;
use App\Platform\Operations\Installation\Application\EnvironmentManager;
use App\Platform\Operations\Installation\Application\InstallationState;
use App\Platform\Operations\Installation\Http\Requests\DatabaseEnvironmentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * The wizard's database step, in both directions: what the form should be
 * prefilled with, and what happens once it comes back.
 */
class DatabaseConfigurationController extends Controller
{
    /**
     * @var EnvironmentManager
     */
    protected $EnvironmentManager;

    public function __construct(EnvironmentManager $environmentManager)
    {
        $this->EnvironmentManager = $environmentManager;
    }

    /**
     * Caches go first so the manager works against the environment as it sits
     * on disk. A clean write is followed by building the installation out in
     * place: public storage symlink, the whole migration chain with seeders,
     * and the version stamp.
     *
     * The application key is deliberately not regenerated here. Rotating it
     * mid-wizard invalidates the session the browser is holding, which surfaces
     * as a token mismatch on the very next step; an instance that needs a fresh
     * key generates it before the wizard runs.
     */
    public function saveDatabaseEnvironment(DatabaseEnvironmentRequest $request)
    {
        Artisan::call('config:clear');
        Artisan::call('cache:clear');

        $results = $this->EnvironmentManager->saveDatabaseVariables($request);

        if (array_key_exists('success', $results)) {
            Artisan::call('optimize:clear');
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('storage:link');
            Artisan::call('migrate --seed --force');

            InstallationState::setCurrentVersion();
        }

        return response()->json($results);
    }

    /**
     * Defaults for the database form. The driver comes from the query string,
     * falling back to whatever the runtime is configured with.
     *
     * A driver with no arm of its own is echoed back with the server defaults
     * rather than an empty config: the wizard chooses which form to render from
     * database_connection, so answering with nothing left the step blank and
     * the install stuck (as a DB_CONNECTION=mariadb compose file once did).
     *
     * The prefill key is database_host while the form posts back
     * database_hostname. The mismatch is what the front end expects.
     */
    public function getDatabaseEnvironment(Request $request)
    {
        $connection = $request->connection ?? config('database.default');

        $databaseData = match ($connection) {
            'sqlite' => [
                'database_connection' => 'sqlite',
                'database_name' => config('database.connections.sqlite.database') ?: 'storage/app/database.sqlite',
            ],
            'pgsql' => [
                'database_connection' => 'pgsql',
                'database_host' => '127.0.0.1',
                'database_port' => 5432,
            ],
            default => [
                'database_connection' => $connection,
                'database_host' => '127.0.0.1',
                'database_port' => 3306,
            ],
        };

        return response()->json([
            'config' => $databaseData,
            'success' => true,
        ]);
    }
}
