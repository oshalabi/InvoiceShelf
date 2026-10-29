<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings as FrameworkTrimStrings;

/**
 * Application hook into the framework's whitespace-trimming pass over the
 * request payload.
 */
class TrimStrings extends FrameworkTrimStrings
{
    /**
     * Input keys handed through untouched, since a leading or trailing space
     * is a legitimate part of the value.
     *
     * Note this replaces the framework list rather than extending it, so
     * `current_password` is trimmed here even though the framework spares it.
     *
     * @var array
     */
    protected $except = [
        'password',
        'password_confirmation',
    ];
}
