<?php

namespace App\Domains\Taxation;

use App\Domains\Taxation\Models\TaxType;
use App\Domains\Taxation\Policies\TaxTypePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class TaxationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(TaxType::class, TaxTypePolicy::class);
    }
}
