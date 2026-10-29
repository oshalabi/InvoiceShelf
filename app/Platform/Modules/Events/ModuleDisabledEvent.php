<?php

namespace App\Platform\Modules\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Announces that a module has been switched off for this installation.
 */
class ModuleDisabledEvent
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
