<?php

namespace App\Support\Net;

use RuntimeException;

/**
 * Thrown by PrivateNetworkGuard::assertAllowed() when an outbound URL targets a
 * private, loopback, link-local, or otherwise non-publicly-routable address.
 *
 * Runtime callers (HTTP drivers) catch this and translate it into their own
 * domain exception so the failure surfaces cleanly to the user.
 */
class BlockedUrlException extends RuntimeException {}
