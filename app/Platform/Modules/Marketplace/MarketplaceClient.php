<?php

namespace App\Platform\Modules\Marketplace;

use App\Platform\Modules\Models\MarketplaceCredential;
use App\Platform\Operations\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MarketplaceClient
{
    private const API_PREFIX = 'api/marketplace/v1';

    public function catalog(): Response
    {
        return $this->request()->get(self::API_PREFIX.'/modules', [
            'channel' => config('invoiceshelf.marketplace.channel', 'stable'),
        ]);
    }

    public function module(string $slug): Response
    {
        return $this->request()->get(self::API_PREFIX.'/modules/'.$slug, [
            'channel' => config('invoiceshelf.marketplace.channel', 'stable'),
        ]);
    }

    public function beginPairing(): Response
    {
        return $this->request()->post(self::API_PREFIX.'/device/code', [
            'installation_name' => (string) config('app.name', 'InvoiceShelf').' — '.parse_url((string) config('app.url'), PHP_URL_HOST),
            'invoiceshelf_version' => (string) Setting::getSetting('version'),
            'module_api_version' => (string) config('invoiceshelf.marketplace.module_api_version'),
            'php_version' => PHP_VERSION,
            'extensions' => collect(get_loaded_extensions())
                ->map(fn (string $extension): string => 'ext-'.str_replace(' ', '-', strtolower($extension)))
                ->filter(fn (string $extension): bool => preg_match('/^ext-[a-z0-9][a-z0-9_-]*$/', $extension) === 1)
                ->unique()
                ->sort()->values()->all(),
        ]);
    }

    public function pollPairing(string $deviceCode): Response
    {
        return $this->request()->post(self::API_PREFIX.'/device/token', [
            'device_code' => $deviceCode,
        ]);
    }

    public function revokeInstallation(): Response
    {
        return $this->request()->delete(self::API_PREFIX.'/device');
    }

    public function release(string $slug, string $version, string $channel): Response
    {
        return $this->request()->post(self::API_PREFIX."/modules/{$slug}/releases/{$version}/download", [
            'channel' => $channel,
        ]);
    }

    /**
     * Signed artifact URLs are intentionally fetched with a separate request:
     * marketplace credentials must never be sent to an object-storage host.
     */
    public function artifact(string $url, string $destination): Response
    {
        if (! $this->allowsArtifactUrl($url)) {
            throw new RuntimeException('Marketplace returned an unsafe artifact URL.');
        }

        return Http::withOptions(['verify' => true, 'allow_redirects' => false])
            ->timeout(120)
            ->sink($destination)
            ->get($url);
    }

    private function allowsArtifactUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts) || isset($parts['user'], $parts['pass']) || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (strtolower($parts['scheme']) === 'https') {
            return true;
        }

        $base = parse_url((string) config('invoiceshelf.base_url'));

        return strtolower($parts['scheme']) === 'http'
            && is_array($base)
            && strtolower((string) ($base['scheme'] ?? '')) === 'http'
            && strtolower($parts['host']) === strtolower((string) ($base['host'] ?? ''))
            && ($parts['port'] ?? 80) === ($base['port'] ?? 80);
    }

    private function request(): PendingRequest
    {
        $request = Http::baseUrl(rtrim((string) config('invoiceshelf.base_url'), '/'))
            ->acceptJson()
            ->timeout(30)
            ->connectTimeout(10)
            ->withOptions(['verify' => true, 'allow_redirects' => false])
            ->withHeaders([
                'Referer' => url('/'),
                'invoiceshelf' => (string) Setting::getSetting('version'),
            ]);

        $credential = MarketplaceCredential::query()->latest('id')->first();

        if ($credential !== null && ($credential->expires_at === null || $credential->expires_at->isFuture())) {
            $request = $request->withToken(Crypt::decryptString($credential->credential));
        }

        return $request;
    }
}
