<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as FrameworkTrustProxies;
use Illuminate\Http\Request;

/**
 * Teaches the request object which upstream proxies may rewrite the client's
 * address, host, port and scheme.
 */
class TrustProxies extends FrameworkTrustProxies
{
    /**
     * Proxies the application accepts forwarded headers from, resolved lazily
     * by {@see self::proxies()}.
     *
     * @var array
     */
    protected $proxies;

    /**
     * Bitmask of the forwarding headers that are honoured.
     *
     * @var array
     */
    protected $headers = Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO
        | Request::HEADER_X_FORWARDED_AWS_ELB;

    /**
     * Resolve the trusted proxy list.
     *
     * Defaults to trusting every hop, which suits the containerised installs
     * that sit behind an operator-controlled reverse proxy.
     *
     * @return string|array|null
     */
    protected function proxies()
    {
        $this->proxies = env('TRUSTED_PROXIES', '*');

        return $this->proxies;
    }
}
