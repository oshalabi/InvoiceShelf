<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as FrameworkEncryptCookies;

/**
 * Application hook into the framework's cookie encryption pass.
 *
 * It exists so the app owns the opt-out list; nothing else is customised.
 */
class EncryptCookies extends FrameworkEncryptCookies
{
    /**
     * Whether cookie payloads are run through PHP's serializer before being
     * encrypted. Left off, matching the framework default.
     *
     * @var bool
     */
    protected static $serialize = false;

    /**
     * Cookie names that travel in clear text. Nothing is exempt right now.
     *
     * @var array
     */
    protected $except = [
        //
    ];
}
