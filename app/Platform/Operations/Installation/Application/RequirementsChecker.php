<?php

namespace App\Platform\Operations\Installation\Application;

use Illuminate\Support\Str;
use PDO;
use SQLite3;

/**
 * Probes the runtime for the pieces the installer refuses to continue without:
 * PHP extensions, web server modules and the version of whichever database
 * engine the operator picked on the wizard's database step.
 */
class RequirementsChecker
{
    /**
     * Floor used only when a caller supplies none of its own. The real floor
     * ships in config/installer.php.
     */
    private const FALLBACK_MIN_PHP_VERSION = '7.0.0';

    /**
     * Evaluate a grouped requirement list. Only the "php" and "apache" groups
     * carry meaning; anything else is dropped, as is a group that produced no
     * verdicts at all. The top level "errors" flag appears only once something
     * has actually failed.
     */
    public function check(array $requirements): array
    {
        $report = [];
        $missing = false;

        foreach ($requirements as $group => $names) {
            $verdicts = match ($group) {
                'php' => $this->probeExtensions($names),
                'apache' => $this->probeApacheModules($names),
                default => [],
            };

            if ($verdicts === []) {
                continue;
            }

            $report['requirements'][$group] = $verdicts;

            if (in_array(false, $verdicts, true)) {
                $missing = true;
            }
        }

        if ($missing) {
            $report['errors'] = true;
        }

        return $report;
    }

    /**
     * Compare the running interpreter against a floor, reporting both the raw
     * version string and its leading numeric part.
     */
    public function checkPHPVersion(?string $minPhpVersion = null): array
    {
        $floor = ($minPhpVersion !== null && $minPhpVersion !== '')
            ? $minPhpVersion
            : $this->getMinPhpVersion();

        $running = $this->numericPhpVersion();

        return [
            'full' => PHP_VERSION,
            'current' => $running,
            'minimum' => $floor,
            'supported' => version_compare($running, $floor) >= 0,
        ];
    }

    /**
     * Compare a live MySQL/MariaDB connection against the floor configured for
     * whichever of the two the server banner reports.
     */
    public function checkMysqlVersion($conn): array
    {
        $banner = $conn->getAttribute(PDO::ATTR_SERVER_VERSION);

        $floor = Str::contains($banner, 'MariaDB')
            ? config('invoiceshelf.min_mariadb_version')
            : config('invoiceshelf.min_mysql_version');

        return $this->versionVerdict($this->queryMysqlVersion($conn), $floor);
    }

    /**
     * Compare the bundled SQLite library against a floor.
     */
    public function checkSqliteVersion(?string $minSqliteVersion = null): array
    {
        return $this->versionVerdict(SQLite3::version()['versionString'], $minSqliteVersion);
    }

    /**
     * Compare a live PostgreSQL connection against a floor.
     */
    public function checkPgsqlVersion($conn, ?string $minPgsqlVersion = null): array
    {
        return $this->versionVerdict(pg_version($conn)['server'], $minPgsqlVersion);
    }

    /**
     * Default PHP floor for callers that pass none.
     */
    protected function getMinPhpVersion(): string
    {
        return self::FALLBACK_MIN_PHP_VERSION;
    }

    /**
     * @return array<string, bool>
     */
    private function probeExtensions(array $extensions): array
    {
        $verdicts = [];

        foreach ($extensions as $extension) {
            $verdicts[$extension] = extension_loaded($extension);
        }

        return $verdicts;
    }

    /**
     * Module introspection is only available under mod_php; without it there
     * is nothing to report, so the group is skipped rather than failed.
     *
     * @return array<string, bool>
     */
    private function probeApacheModules(array $modules): array
    {
        if (! function_exists('apache_get_modules')) {
            return [];
        }

        $enabled = apache_get_modules();
        $verdicts = [];

        foreach ($modules as $module) {
            $verdicts[$module] = in_array($module, $enabled);
        }

        return $verdicts;
    }

    /**
     * The shared shape of every database version report.
     */
    private function versionVerdict(?string $current, ?string $minimum): array
    {
        return [
            'current' => $current,
            'minimum' => $minimum,
            'supported' => version_compare($current, $minimum) >= 0,
        ];
    }

    /**
     * PHP_VERSION with any suffix such as "-1+ubuntu" trimmed away.
     */
    private function numericPhpVersion(): string
    {
        preg_match("#^\d+(\.\d+)*#", PHP_VERSION, $leading);

        return $leading[0];
    }

    private function queryMysqlVersion($pdo): string
    {
        preg_match("/^[0-9\.]+/", $pdo->query('select version()')->fetchColumn(), $leading);

        return $leading[0];
    }
}
