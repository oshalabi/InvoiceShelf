<?php

namespace App\Domains\Contacts;

use App\Adapters\Contacts\EloquentCustomerDataPurger;
use App\Adapters\Contacts\EloquentCustomerPortalDashboardProvider;
use App\Adapters\Contacts\EloquentCustomerStatsProvider;
use App\Adapters\Contacts\MediaLibraryCustomerAvatarManager;
use App\Domains\Contacts\Contracts\CustomerAvatarManager;
use App\Domains\Contacts\Contracts\CustomerDataPurger;
use App\Domains\Contacts\Contracts\CustomerPortalDashboardProvider;
use App\Domains\Contacts\Contracts\CustomerStatsProvider;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Contacts\Policies\CustomerPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ContactsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CustomerAvatarManager::class, MediaLibraryCustomerAvatarManager::class);
        $this->app->bind(CustomerDataPurger::class, EloquentCustomerDataPurger::class);
        $this->app->bind(CustomerPortalDashboardProvider::class, EloquentCustomerPortalDashboardProvider::class);
        $this->app->bind(CustomerStatsProvider::class, EloquentCustomerStatsProvider::class);
    }

    public function boot(): void
    {
        Gate::policy(Customer::class, CustomerPolicy::class);
        $bulkCustomerDelete = [CustomerPolicy::class, 'deleteMultiple'];
        Gate::define('delete multiple customers', $bulkCustomerDelete);
    }
}
