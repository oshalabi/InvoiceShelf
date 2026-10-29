<?php

namespace App\Platform\Operations\Update;

use App\Platform\Operations\Models\Setting;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

/**
 * Talks to the release server that publishes InvoiceShelf builds.
 *
 * Every call is a plain TLS-verified GET against the configured base URL. The
 * server identifies the caller through a product header carrying the version
 * this instance currently runs, so requests must keep going out over a Guzzle
 * client rather than the framework HTTP facade.
 */
trait CallsReleaseServer
{
    /**
     * Fetch a release-server resource, or null when the request never landed.
     *
     * @param  string  $url  path relative to the release-server base URL
     * @param  array  $data  extra Guzzle request options (timeouts, redirects, ...)
     * @param  string|null  $token  optional bearer credential
     * @return ResponseInterface|null
     */
    protected static function getRemote($url, $data = [], $token = null)
    {
        $options = $data;

        // Error statuses are part of the answer here, not an exception.
        $options['http_errors'] = false;

        $options['headers'] = [
            'Accept' => 'application/json',
            'Referer' => url('/'),
            'Authorization' => "Bearer {$token}",
            'invoiceshelf' => Setting::getSetting('version'),
        ];

        $client = new Client([
            'verify' => true,
            'base_uri' => config('invoiceshelf.base_url').'/',
        ]);

        try {
            return $client->get($url, $options);
        } catch (GuzzleException $e) {
            return null;
        }
    }
}
