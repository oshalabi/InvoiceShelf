<?php

namespace App\Platform\Modules\Marketplace;

use App\Platform\Modules\Models\MarketplaceOperation;
use Throwable;

class MarketplaceOperationLease
{
    public function acquire(?string $slug, ?string $version, ?string $channel): ?MarketplaceOperation
    {
        $lock = 'marketplace-install';

        MarketplaceOperation::query()->where('lock_name', $lock)->where('expires_at', '<', now())->update([
            'lock_name' => null,
            'status' => 'failed',
            'error' => 'Marketplace operation lease expired.',
            'finished_at' => now(),
        ]);

        try {
            return MarketplaceOperation::query()->create([
                'lock_name' => $lock,
                'slug' => $slug,
                'version' => $version,
                'channel' => $channel,
                'status' => 'running',
                'started_at' => now(),
                'expires_at' => now()->addSeconds((int) config('invoiceshelf.marketplace.lease_seconds')),
            ]);
        } catch (Throwable) {
            return null;
        }
    }

    public function finish(MarketplaceOperation $operation, string $status, ?string $error = null): void
    {
        $operation->update([
            'lock_name' => null,
            'status' => $status,
            'error' => $error,
            'finished_at' => now(),
            'expires_at' => now(),
        ]);
    }
}
