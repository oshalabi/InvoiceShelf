<?php

namespace App\Platform\Modules\Http\Resources;

use App\Platform\Modules\Models\Module as ModelsModule;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\File;

class ModuleResource extends JsonResource
{
    /**
     * Merge one marketplace catalog row with locally installed module state.
     *
     * @param  Request  $request
     * @return array|Arrayable|\JsonSerializable
     */
    public function toArray($request): array
    {
        $moduleName = data_get($this->resource, 'module_name');
        $installedModule = is_string($moduleName)
            ? ModelsModule::where('name', $moduleName)->first()
            : null;
        $release = data_get($this->resource, 'release');
        $latestVersion = data_get($this->resource, 'latest_module_version') ?? data_get($release, 'version');
        $compatibility = data_get($this->resource, 'compatibility') ?? data_get($release, 'compatibility');
        $access = data_get($this->resource, 'access', 'free');

        return [
            'id' => data_get($this->resource, 'id'),
            'average_rating' => data_get($this->resource, 'average_rating'),
            'cover' => data_get($this->resource, 'cover'),
            'slug' => data_get($this->resource, 'slug'),
            'module_name' => $moduleName,
            'access_tier' => data_get($this->resource, 'access_tier') ?? ($access === 'paid' ? 'premium' : 'public'),
            'access' => $access,
            'entitlement' => data_get($this->resource, 'entitlement'),
            'compatibility' => $compatibility,
            'compatible' => data_get($this->resource, 'compatible'),
            'release_state' => data_get($this->resource, 'release_state') ?? data_get($release, 'state') ?? 'published',
            'yanked_reason' => data_get($this->resource, 'yanked_reason') ?? data_get($release, 'yanked_reason'),
            'channel' => data_get($this->resource, 'channel') ?? data_get($release, 'channel') ?? 'stable',
            'faq' => data_get($this->resource, 'faq'),
            'highlights' => data_get($this->resource, 'highlights'),
            'installed_module_version' => $this->getInstalledModuleVersion($installedModule),
            'installed_module_version_updated_at' => $this->getInstalledModuleUpdatedAt($installedModule),
            'latest_module_version' => $latestVersion,
            'latest_module_version_updated_at' => data_get($this->resource, 'latest_module_version_updated_at') ?? data_get($release, 'published_at'),
            'latest_min_invoiceshelf_version' => data_get($this->resource, 'latest_min_invoiceshelf_version'),
            'latest_module_checksum_sha256' => data_get($this->resource, 'latest_module_checksum_sha256') ?? data_get($release, 'artifact.sha256'),
            'is_dev' => (bool) data_get($this->resource, 'is_dev', false),
            'license' => data_get($this->resource, 'license', 'AGPL-3.0-only'),
            'long_description' => data_get($this->resource, 'long_description'),
            'monthly_price' => data_get($this->resource, 'monthly_price'),
            'name' => data_get($this->resource, 'name'),
            'purchased' => data_get($this->resource, 'purchased') ?? ($access === 'free' || (bool) data_get($this->resource, 'entitlement.active', false)),
            'purchase_url' => data_get($this->resource, 'purchase_url'),
            'reviews' => data_get($this->resource, 'reviews', []),
            'screenshots' => data_get($this->resource, 'screenshots'),
            'short_description' => data_get($this->resource, 'short_description'),
            'type' => data_get($this->resource, 'type'),
            'yearly_price' => data_get($this->resource, 'yearly_price'),
            'author_name' => data_get($this->resource, 'author_name') ?? data_get($this->resource, 'author.name'),
            'author_avatar' => data_get($this->resource, 'author_avatar') ?? data_get($this->resource, 'author.avatar'),
            'installed' => $this->moduleInstalled($installedModule),
            'enabled' => $this->moduleEnabled($installedModule),
            'supports_data_cleanup' => $this->supportsDataCleanup($installedModule),
            'update_available' => $this->updateAvailable($installedModule, $latestVersion),
            'video_link' => data_get($this->resource, 'video_link') ?? data_get($this->resource, 'video.url'),
            'video_thumbnail' => data_get($this->resource, 'video_thumbnail') ?? data_get($this->resource, 'video.thumbnail'),
            'links' => data_get($this->resource, 'links'),
        ];
    }

    public function getInstalledModuleVersion(?ModelsModule $installedModule): ?string
    {
        if ($installedModule && $installedModule->installed) {
            return $installedModule->version;
        }

        return null;
    }

    public function getInstalledModuleUpdatedAt(?ModelsModule $installedModule): ?string
    {
        if ($installedModule && $installedModule->installed) {
            return $installedModule->updated_at?->toIso8601String();
        }

        return null;
    }

    public function moduleInstalled(?ModelsModule $installedModule): bool
    {
        return (bool) ($installedModule?->installed);
    }

    public function moduleEnabled(?ModelsModule $installedModule): bool
    {
        return (bool) ($installedModule?->installed && $installedModule?->enabled);
    }

    public function updateAvailable(?ModelsModule $installedModule, mixed $latestVersion): bool
    {
        if (! $installedModule || ! $installedModule->installed) {
            return false;
        }

        if (! is_string($latestVersion)) {
            return false;
        }

        return version_compare($installedModule->version, $latestVersion, '<');
    }

    public function supportsDataCleanup(?ModelsModule $installedModule): bool
    {
        if (! $installedModule?->installed) {
            return false;
        }

        $path = base_path('Modules/'.$installedModule->name.'/module.json');
        $metadata = File::isFile($path) ? json_decode((string) File::get($path), true) : null;
        $uninstall = is_array($metadata) ? ($metadata['uninstall'] ?? null) : null;
        $cleanup = is_array($uninstall) ? ($uninstall['data_cleanup'] ?? null) : null;
        $contract = 'InvoiceShelf\\Modules\\Contracts\\DataCleanup';

        if (! (is_array($metadata)
            && ($metadata['schema_version'] ?? null) === 2
            && ($metadata['migration_policy'] ?? null) === 'reversible'
            && is_array($uninstall)
            && array_keys($uninstall) === ['data_cleanup']
            && is_string($cleanup)
            && interface_exists($contract))) {
            return false;
        }

        try {
            $reflection = new \ReflectionClass($cleanup);

            return ! $reflection->isAbstract()
                && $reflection->isInstantiable()
                && is_a($cleanup, $contract, true)
                && $reflection->hasMethod('cleanup')
                && $reflection->getMethod('cleanup')->isPublic()
                && ! $reflection->getMethod('cleanup')->isStatic();
        } catch (\Throwable) {
            return false;
        }
    }
}
