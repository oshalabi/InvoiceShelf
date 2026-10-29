<?php

namespace App\Rules;

use App\Support\Net\PrivateNetworkGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects URLs that target a private, loopback, link-local, or otherwise
 * non-publicly-routable address — an SSRF guard for admin/owner-supplied base
 * URLs and endpoints the server fetches with credentials attached.
 *
 * Pair with the `url` rule, which validates syntax; this rule adds the
 * network-safety check on top. Empty values pass through so it composes with
 * `nullable`. Hostnames that do not resolve are allowed here (fail-open); the
 * authoritative block happens in the runtime driver guard at request time.
 */
class PublicHttpUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        if (PrivateNetworkGuard::blockedReason($value) !== null) {
            $fail('The :attribute must be a publicly reachable URL, not a private or reserved address.');
        }
    }
}
