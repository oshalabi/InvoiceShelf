<?php

namespace App\Platform\Modules\Runtime;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Nwidart\Modules\Activators\FileActivator;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Nwidart\Modules\Module;
use Throwable;

/**
 * Instance activation is durable database state. The file activator is used
 * only while the database is unavailable during application bootstrap.
 */
class DatabaseActivator implements ActivatorInterface
{
    private FileActivator $fallback;

    public function __construct(Container $app)
    {
        $this->fallback = new FileActivator($app);
    }

    public function enable(Module $module): void
    {
        $this->setActiveByName($module->getName(), true);
    }

    public function disable(Module $module): void
    {
        $this->setActiveByName($module->getName(), false);
    }

    public function hasStatus(Module|string $module, bool $status): bool
    {
        $name = $module instanceof Module ? $module->getName() : $module;

        if (! $this->databaseReady()) {
            return $this->fallback->hasStatus($name, $status);
        }

        $installed = DB::table('modules')->where('name', $name)->first();

        return $installed === null ? $status === false : (bool) $installed->enabled === $status;
    }

    public function setActive(Module $module, bool $active): void
    {
        $this->setActiveByName($module->getName(), $active);
    }

    public function setActiveByName(string $name, bool $active): void
    {
        if (! $this->databaseReady()) {
            $this->fallback->setActiveByName($name, $active);

            return;
        }

        $module = DB::table('modules')->where('name', $name)->first();
        if ($module !== null) {
            DB::table('modules')->where('name', $name)->update(['enabled' => $active, 'updated_at' => now()]);

            return;
        }

        DB::table('modules')->insert([
            'name' => $name,
            'version' => '0.0.0',
            'installed' => true,
            'enabled' => $active,
            'state' => 'installed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function delete(Module $module): void
    {
        if (! $this->databaseReady()) {
            $this->fallback->delete($module);

            return;
        }

        throw new \LogicException('Direct module deletion is disabled. Use module:uninstall instead.');
    }

    public function reset(): void
    {
        if (! $this->databaseReady()) {
            $this->fallback->reset();

            return;
        }

        DB::table('modules')->update(['enabled' => false, 'updated_at' => now()]);
    }

    private function databaseReady(): bool
    {
        if (! app()->bound('db')) {
            return false;
        }

        try {
            return Schema::hasTable('modules') && Schema::hasColumn('modules', 'state');
        } catch (Throwable) {
            return false;
        }
    }
}
