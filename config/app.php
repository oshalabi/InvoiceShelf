<?php

use Illuminate\Support\Facades\Facade;

return [

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | The default timezone for date and date-time functions. Laravel's own
    | fallback is the literal string 'UTC' rather than an env() lookup, so
    | without this key APP_TIMEZONE has no effect at all and scheduled tasks
    | and recurring invoices always run on UTC.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'UTC'),

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    |
    | This array of class aliases will be registered when this application
    | is started. You may add any additional class aliases which should
    | be loaded to the array. For speed, all aliases are lazy loaded.
    |
    */

    'aliases' => Facade::defaultAliases()->merge([
        'Menu' => Lavary\Menu\Facade::class,
    ])->toArray(),

];
