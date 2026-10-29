<?php

namespace App\Platform\Operations\Installation\Application;

use App\Platform\Operations\Installation\Http\Requests\DatabaseEnvironmentRequest;
use App\Platform\Operations\Installation\Http\Requests\DomainEnvironmentRequest;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Owns every write the installation wizard makes to the environment file: the
 * database step, the domain step, and the line-wise editing rules both of them
 * go through.
 */
class EnvironmentManager
{
    private string $envPath;

    /**
     * @var string
     */
    private $delimiter = "\n";

    /**
     * The argument is vestigial: the only file the wizard ever edits is the
     * one sitting at the application root.
     */
    public function __construct($path = null)
    {
        $this->envPath = base_path('.env');
    }

    /**
     * Apply a map of variable name => value to the environment file. Names the
     * file already declares are rewritten where they stand; the rest are
     * appended. Returns false when there is nothing to write, or no file to
     * write it into.
     *
     * @return bool
     */
    public function updateEnv(array $data)
    {
        if ($data === [] || ! is_file($this->envPath)) {
            return false;
        }

        $lines = explode($this->delimiter, (string) file_get_contents($this->envPath));

        foreach ($data as $name => $value) {
            $lines = $this->applyDeclaration($lines, (string) $name, $value);
        }

        file_put_contents($this->envPath, implode($this->delimiter, $lines));

        return true;
    }

    /**
     * Rewrite every line declaring $name, or append one when none does. A
     * line's name is whatever sits in front of its first "=".
     */
    private function applyDeclaration(array $lines, string $name, $value): array
    {
        $declaration = $name.'='.$this->encode($value);
        $found = false;

        foreach ($lines as $index => $line) {
            if (explode('=', $line, 2)[0] === $name) {
                $lines[$index] = $declaration;
                $found = true;
            }
        }

        if (! $found) {
            $lines[] = $declaration;
        }

        return $lines;
    }

    /**
     * Encodes value for .env
     *
     * @return mixed|string
     */
    private function encode($str)
    {
        // Convert to string if not already
        $str = (string) $str;

        // If the value is already properly quoted, return as is
        if (strlen($str) >= 2 && $str[0] === '"' && $str[strlen($str) - 1] === '"') {
            return $str;
        }

        // Check if the value contains characters that need quoting
        // Using a character class regex to properly match special characters
        $specialChars = '\^\'£$%&*()}{@#~?><,|=\-_+¬!';
        $needsQuoting = (
            strpos($str, ' ') !== false ||
            preg_match('/['.preg_quote($specialChars, '/').']/', $str)
        );

        if ($needsQuoting) {
            // Escape any existing double quotes in the string
            $str = str_replace('"', '\\"', $str);
            $str = '"'.$str.'"';
        }

        return $str;
    }

    /**
     * The database step. Nothing is written until the submitted credentials
     * have been proven to open a connection and the target database has been
     * shown to be free of a previous installation.
     *
     * @return array
     */
    public function saveDatabaseVariables(DatabaseEnvironmentRequest $request)
    {
        $appUrl = $request->get('app_url');

        if ($appUrl !== config('app.url')) {
            config(['app.url' => $appUrl]);
        }

        $driver = $request->get('database_connection');

        // Derived against the URL just adopted above, from the host the wizard
        // itself is being served on.
        [$statefulDomains, $sessionDomain] = $this->getDomains($request->getHttpHost());

        $variables = [
            'APP_URL' => $appUrl,
            'APP_LOCALE' => $request->get('app_locale'),
            'DB_CONNECTION' => $driver,
            'SESSION_DOMAIN' => $sessionDomain,
        ];

        if ($statefulDomains !== null) {
            $variables['SANCTUM_STATEFUL_DOMAINS'] = $statefulDomains;
        }

        if ($driver === 'sqlite') {
            $unsupported = $this->sqliteSupportFailure();

            if ($unsupported !== null) {
                return [
                    'error_message' => $unsupported,
                ];
            }

            $variables['DB_DATABASE'] = $request->get('database_name');

            $this->createSqliteDatabase(
                $this->resolveSqliteDatabasePath($variables['DB_DATABASE'])
            );
        } elseif ($request->has('database_username') && $request->has('database_password')) {
            // Server credentials are only recorded once both halves are on the
            // request. The password is not a validation requirement, so a
            // passwordless account submits it as an empty string.
            $variables['DB_HOST'] = $request->get('database_hostname');
            $variables['DB_PORT'] = $request->get('database_port');
            $variables['DB_DATABASE'] = $request->get('database_name');
            $variables['DB_USERNAME'] = $request->get('database_username');
            $variables['DB_PASSWORD'] = $request->get('database_password');
        }

        try {
            $this->openSubmittedConnection($request);

            if ($request->get('database_overwrite')) {
                Artisan::call('db:wipe --force');
            }

            // Checked before the environment file is touched: refusing here
            // leaves the instance exactly as it was found.
            if (Schema::hasTable('users')) {
                return [
                    'error' => 'database_should_be_empty',
                ];
            }
        } catch (Exception $e) {
            return [
                'error_message' => $e->getMessage(),
            ];
        }

        try {
            $this->updateEnv($variables);
        } catch (Exception $e) {
            return [
                'error' => 'database_variables_save_error',
            ];
        }

        return [
            'success' => 'database_variables_save_successfully',
        ];
    }

    /**
     * Laravel's SQLite grammar needs 3.35 or newer. Returns the sentence to
     * hand back to the wizard, or null when the extension is fit for use.
     */
    private function sqliteSupportFailure(): ?string
    {
        $minimum = '3.35.0';

        if (! extension_loaded('sqlite3') || ! class_exists('\SQLite3') || ! method_exists('\SQLite3', 'version')) {
            return sprintf('SQLite3 is not present. Please install SQLite >=%s and retry.', $minimum);
        }

        $found = \SQLite3::version()['versionString'] ?? '';

        if ($found !== '' && version_compare($found, $minimum, '<')) {
            return sprintf('The minimum SQLite version is %s. Your current SQLite version is %s which is not supported. Please upgrade SQLite and retry.', $minimum, $found);
        }

        return null;
    }

    /**
     * A fresh SQLite install points at a file that does not exist yet: lay down
     * the bundled empty database, digging out the directory the user asked for
     * on the way, so an absolute path outside the project still works.
     */
    private function createSqliteDatabase(string $path): void
    {
        if (file_exists($path)) {
            return;
        }

        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        copy(database_path('stubs/sqlite.empty.db'), $path);
    }

    /**
     * Narrow the runtime database configuration down to the single connection
     * described by the form and open it. Bad credentials, an unreachable
     * server or a missing database all surface as a driver exception.
     *
     * @return \PDO
     */
    private function openSubmittedConnection(DatabaseEnvironmentRequest $request)
    {
        $driver = $request->get('database_connection');
        $database = $request->get('database_name');

        $settings = array_merge(config("database.connections.{$driver}"), [
            'driver' => $driver,
            'database' => $driver === 'sqlite'
                ? $this->resolveSqliteDatabasePath($database)
                : $database,
        ]);

        if ($driver !== 'sqlite' && $request->has('database_username') && $request->has('database_password')) {
            $settings['username'] = $request->get('database_username');
            $settings['password'] = $request->get('database_password');
            $settings['host'] = $request->get('database_hostname');
            $settings['port'] = $request->get('database_port');
        }

        config([
            'database' => [
                'migrations' => 'migrations',
                'default' => $driver,
                'connections' => [$driver => $settings],
            ],
        ]);

        DB::purge($driver);

        return DB::connection($driver)->getPdo();
    }

    private function resolveSqliteDatabasePath(?string $databasePath): string
    {
        $databasePath = trim((string) $databasePath);

        if ($databasePath === '') {
            return storage_path('app/database.sqlite');
        }

        if ($this->isAbsolutePath($databasePath)) {
            return $databasePath;
        }

        return base_path($databasePath);
    }

    /**
     * Absolute means a leading separator, or a Windows drive prefix.
     */
    private function isAbsolutePath(string $path): bool
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return true;
        }

        return preg_match('~^[A-Za-z]:[\\\\/]~', $path) === 1;
    }

    /**
     * The domain step: rewrite the session domain, and the stateful-domain
     * list unless writing it would be a no-op.
     *
     * @return array
     */
    public function saveDomainVariables(DomainEnvironmentRequest $request)
    {
        try {
            [$statefulDomains, $sessionDomain] = $this->getDomains(
                $request->get('app_domain')
            );

            $variables = [
                'SESSION_DOMAIN' => $sessionDomain,
            ];

            if ($statefulDomains !== null) {
                $variables['SANCTUM_STATEFUL_DOMAINS'] = $statefulDomains;
            }

            $this->updateEnv($variables);
        } catch (Exception $e) {
            return [
                'error' => 'domain_verification_failed',
            ];
        }

        return [
            'success' => 'domain_variable_save_successfully',
        ];
    }

    private function getDomains(string $requestDomain): array
    {
        $appUrl = config('app.url');

        $port = parse_url($appUrl, PHP_URL_PORT);
        $currentDomain = parse_url($appUrl, PHP_URL_HOST).(
            $port ? ':'.$port : ''
        );

        $requestHost = parse_url($requestDomain, PHP_URL_HOST) ?: $requestDomain;

        $isSame = $currentDomain === $requestDomain;

        return [
            $isSame && env('SANCTUM_STATEFUL_DOMAINS', false) === false ?
            null : $requestDomain,
            $isSame && env('SESSION_DOMAIN', false) === null ?
                null : $requestHost,
        ];
    }
}
