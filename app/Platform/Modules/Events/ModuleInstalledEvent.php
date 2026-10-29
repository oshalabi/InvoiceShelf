<?php

namespace App\Platform\Modules\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Announces that a module's files, migrations and registry row are in place.
 */
class ModuleInstalledEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Subject of the announcement. Deliberately untyped: the property and the
     * constructor parameter are a published contract for module listeners.
     */
    public $module;

    public function __construct($module)
    {
        $this->module = $module;
    }
}
