<?php

namespace App\Platform\Operations\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised once an update has been applied and the version setting re-stamped.
 *
 * Carries both sides of the jump so listeners can tell what changed.
 */
class UpdateFinished
{
    use Dispatchable;

    /**
     * @param  string  $old  the version that was running before the update
     * @param  string  $new  the version that is now installed
     */
    public function __construct(
        public $old,
        public $new,
    ) {}
}
