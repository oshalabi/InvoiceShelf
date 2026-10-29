<?php

namespace App\Platform\Modules\Http\Controllers\Admin;

use App\Platform\Http\Controller;
use App\Platform\Modules\Marketplace\MarketplaceClient;
use App\Platform\Modules\Models\MarketplaceCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class MarketplacePairingController extends Controller
{
    public function __construct(private MarketplaceClient $client) {}

    public function start(): JsonResponse
    {
        $this->authorize('manage modules');
        $response = $this->client->beginPairing();
        $data = $response->json();
        if (! $response->successful() || ! is_array($data) || ! is_string($data['device_code'] ?? null)) {
            return response()->json(['error' => 'marketplace_unavailable'], 503);
        }

        $ttl = max(60, (int) ($data['expires_in'] ?? 600));
        Cache::put($this->cacheKey(), $data['device_code'], now()->addSeconds($ttl));

        return response()->json([
            'device_code' => $data['device_code'],
            'user_code' => $data['user_code'] ?? null,
            'verification_uri' => $data['verification_uri'] ?? $data['verification_uri_complete'] ?? null,
            'verification_uri_complete' => $data['verification_uri_complete'] ?? null,
            'expires_in' => $ttl,
            'interval' => max(1, (int) ($data['interval'] ?? 5)),
        ], 201);
    }

    public function poll(): JsonResponse
    {
        $this->authorize('manage modules');
        $deviceCode = Cache::get($this->cacheKey());
        if (! is_string($deviceCode)) {
            return response()->json(['error' => 'pairing_expired'], 422);
        }

        $response = $this->client->pollPairing($deviceCode);
        $data = $response->json();
        if ($response->status() === 428 || ($data['error'] ?? null) === 'authorization_pending') {
            return response()->json(['status' => 'pending']);
        }
        if (! $response->successful() || ! is_array($data) || ! is_string($data['installation_token'] ?? null)) {
            return response()->json(['error' => 'pairing_failed'], 422);
        }

        MarketplaceCredential::query()->delete();
        MarketplaceCredential::query()->create([
            'credential' => Crypt::encryptString($data['installation_token']),
            'device_id' => is_scalar($data['installation']['id'] ?? null) ? (string) $data['installation']['id'] : null,
            'paired_at' => now(),
        ]);
        Cache::forget($this->cacheKey());

        return response()->json(['status' => 'paired']);
    }

    public function status(): JsonResponse
    {
        $this->authorize('manage modules');
        $credential = MarketplaceCredential::query()->latest('id')->first();

        return response()->json([
            'paired' => $credential !== null,
            'expired' => $credential?->expires_at?->isPast() ?? false,
            'paired_at' => $credential?->paired_at?->toIso8601String(),
        ]);
    }

    public function disconnect(): JsonResponse
    {
        $this->authorize('manage modules');
        if (MarketplaceCredential::query()->exists()) {
            // Revocation releases any entitlement activation tied to this
            // installation. Local disconnect still succeeds if the control
            // plane is temporarily unavailable.
            $this->client->revokeInstallation();
        }
        MarketplaceCredential::query()->delete();
        Cache::forget($this->cacheKey());

        return response()->json(['success' => true]);
    }

    private function cacheKey(): string
    {
        return 'marketplace.device-pairing';
    }
}
