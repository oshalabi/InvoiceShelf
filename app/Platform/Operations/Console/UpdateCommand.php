<?php

namespace App\Platform\Operations\Console;

use App\Platform\Operations\Update\Updater;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Applies a release from the command line.
 *
 * Walks the same pipeline the admin UI drives, announcing every stage and
 * bailing out on the first one that fails. Containerized installs are told to
 * pull a new image instead.
 */
class UpdateCommand extends Command
{
    /**
     * Version this instance runs right now.
     */
    public $installed;

    /**
     * Version being installed, or false when there is nothing to do.
     */
    public $version;

    /**
     * Whatever the release-server check handed back.
     */
    public $response;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'core:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically update your InvoiceShelf Core App';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        set_time_limit(3600); // 1 hour

        if (config('invoiceshelf.containerized')) {
            $this->error('The in-app updater is disabled in containerized installs. Upgrade with `docker compose pull`.');

            return;
        }

        $this->installed = $this->getInstalledVersion();
        $this->response = $this->getLatestVersionResponse();
        $this->version = ($this->response) ? $this->response->version : false;

        if ($this->response == 'extension_required') {
            $this->info('Sorry! Your system does not meet the minimum requirements for this update.');
            $this->info('Please retry after installing the required version/extensions.');

            return;
        }

        if (! $this->version) {
            $this->info('No Update Available! You are already on the latest version.');

            return;
        }

        if (! $this->confirm("Do you wish to update to {$this->version}?")) {
            return;
        }

        $archive = $this->download();

        if ($archive === false) {
            return;
        }

        $extracted = $this->unzip($archive);

        if ($extracted === false) {
            return;
        }

        if (! $this->copyFiles($extracted)) {
            return;
        }

        $removals = $this->response->deleted_files ?? null;

        if (! empty($removals) && ! $this->deleteFiles($removals)) {
            return;
        }

        if (! $this->migrateUpdate() || ! $this->finish()) {
            return;
        }

        $this->info('Successfully updated to '.$this->version);
    }

    /**
     * Read the running version off the file shipped with the release.
     */
    public function getInstalledVersion()
    {
        return preg_replace('~[\r\n]+~', '', File::get(base_path('version.md')));
    }

    /**
     * Ask the release server what is available and grade the requirements.
     *
     * @return object|string|false the release, 'extension_required' when this
     *                             machine falls short, false when there is
     *                             nothing newer or the check failed
     */
    public function getLatestVersionResponse()
    {
        $this->info('Your currently installed version is '.$this->installed);
        $this->line('');
        $this->info('Checking for update...');

        try {
            $response = Updater::checkForUpdate($this->installed);

            if ($response->success) {
                $extensions = $response->version->extensions;

                $is_required = false;

                foreach ($extensions as $key => $extension) {
                    if (! $extension) {
                        $is_required = true;
                        $this->info('❌ '.$key);
                    }

                    $this->info('✅ '.$key);
                }

                if ($is_required) {
                    return 'extension_required';
                }

                return $response->version;
            }

            return false;
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return false;
        }
    }

    /**
     * Fetch the release archive.
     */
    public function download()
    {
        return $this->runStep(
            'Downloading update...',
            fn () => Updater::download($this->version, 1),
            'Download exception'
        );
    }

    /**
     * Expand the archive that was just fetched.
     */
    public function unzip($path)
    {
        return $this->runStep(
            'Unzipping update package...',
            fn () => Updater::unzip($path),
            'Unzipping exception'
        );
    }

    /**
     * Lay the extracted release over the installation.
     */
    public function copyFiles($path)
    {
        return $this->runStep('Copying update files...', fn () => Updater::copyFiles($path));
    }

    /**
     * Remove the files the release server flagged as gone.
     */
    public function deleteFiles($files)
    {
        return $this->runStep('Deleting unused old files...', fn () => Updater::deleteFiles($files));
    }

    /**
     * Bring the database schema in line with the new code.
     */
    public function migrateUpdate()
    {
        return $this->runStep('Running Migrations...', fn () => Updater::migrateUpdate());
    }

    /**
     * Stamp the new version and fire the completion event.
     */
    public function finish()
    {
        return $this->runStep('Finishing update...', fn () => Updater::finishUpdate($this->installed, $this->version));
    }

    /**
     * Announce a stage, run it, and report what came back.
     *
     * Without $pathExpected the stage is judged on whether it threw; with it,
     * the stage must hand back a path and that path becomes the return value.
     *
     * @return string|bool
     */
    private function runStep(string $announcement, callable $stage, ?string $pathExpected = null)
    {
        $this->info($announcement);

        try {
            $outcome = $stage();
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return false;
        }

        if ($pathExpected === null) {
            return true;
        }

        if (! is_string($outcome)) {
            $this->error($pathExpected);

            return false;
        }

        return $outcome;
    }
}
