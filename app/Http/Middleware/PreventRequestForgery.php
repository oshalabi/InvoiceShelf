<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as FrameworkPreventRequestForgery;

/**
 * Application hook into the framework's CSRF token check.
 *
 * Only two endpoints opt out, both of them credential posts that are reached
 * before a session token can reasonably be in hand.
 */
class PreventRequestForgery extends FrameworkPreventRequestForgery
{
    /**
     * Whether responses carry the readable XSRF-TOKEN cookie the SPA reads
     * back when signing its own requests.
     *
     * @var bool
     */
    protected $addHttpCookie = true;

    /**
     * Request paths the token check skips.
     *
     * @var array<int, string>
     */
    protected $except = [
        'login',
        'installation/session-login',
    ];
}
