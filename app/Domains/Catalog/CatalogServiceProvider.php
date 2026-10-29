<?php

namespace App\Domains\Catalog;

use App\Adapters\Catalog\TaxationItemTaxManager;
use App\Domains\Catalog\Contracts\ItemTaxManager;
use App\Domains\Catalog\Models\Item;
use App\Domains\Catalog\Models\Unit;
use App\Domains\Catalog\Policies\ItemPolicy;
use App\Domains\Catalog\Policies\UnitPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ItemTaxManager::class, TaxationItemTaxManager::class);
    }

    public function boot(): void
    {
        Gate::policy(Item::class, ItemPolicy::class);
        Gate::policy(Unit::class, UnitPolicy::class);
        $bulkItemDelete = [ItemPolicy::class, 'deleteMultiple'];
        Gate::define('delete multiple items', $bulkItemDelete);
    }
}
