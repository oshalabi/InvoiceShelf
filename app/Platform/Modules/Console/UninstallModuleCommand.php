<?php

namespace App\Platform\Modules\Console;

use App\Platform\Modules\Marketplace\MarketplaceUninstaller;
use Illuminate\Console\Command;

class UninstallModuleCommand extends Command
{
    protected $signature = 'module:uninstall
        {module : The installed module name}
        {--remove-data : Run developer cleanup, reset reversible migrations, then remove module settings}
        {--force : Skip the interactive safety confirmation}';

    protected $description = 'Safely uninstall a marketplace module';

    public function handle(MarketplaceUninstaller $uninstaller): int
    {
        $module = (string) $this->argument('module');
        $removeData = (bool) $this->option('remove-data');

        if (! $this->option('force') && ! $this->confirm(
            $removeData
                ? "This permanently removes {$module} and its data. Continue?"
                : "This removes {$module}'s runtime files but preserves its data. Continue?",
        )) {
            return self::FAILURE;
        }

        $result = $uninstaller->uninstall($module, $removeData, $removeData ? $module : null);
        if (! $result['success']) {
            $this->error('Module uninstall failed: '.$result['error']);

            return self::FAILURE;
        }

        $this->info('Module uninstalled successfully.');

        return self::SUCCESS;
    }
}
