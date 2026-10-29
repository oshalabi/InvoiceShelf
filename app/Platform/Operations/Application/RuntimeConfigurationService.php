<?php

namespace App\Platform\Operations\Application;

use App\Platform\Mail\Contracts\MailConfigurator;
use App\Platform\Operations\Installation\Application\InstallationState;
use App\Platform\Pdf\Contracts\PdfConfigurator;
use App\Platform\Storage\Contracts\StorageConfigurator;

class RuntimeConfigurationService
{
    public function __construct(
        private readonly MailConfigurator $mail,
        private readonly PdfConfigurator $pdf,
        private readonly StorageConfigurator $storage,
    ) {}

    public function apply(): void
    {
        if (! InstallationState::isDbCreated()) {
            return;
        }

        foreach ([$this->mail, $this->pdf, $this->storage] as $configurator) {
            try {
                $configurator->applyGlobalConfig();
            } catch (\Exception) {
                // Installation and migration commands can boot while the schema
                // is incomplete. File configuration remains the safe fallback.
            }
        }
    }
}
