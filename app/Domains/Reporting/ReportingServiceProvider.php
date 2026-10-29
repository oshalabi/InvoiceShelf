<?php

namespace App\Domains\Reporting;

use App\Domains\Reporting\Policies\DashboardPolicy;
use App\Domains\Reporting\Policies\ReportPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ReportingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $reportingAbilities = [
            'view dashboard' => [DashboardPolicy::class, 'view'],
            'view report' => [ReportPolicy::class, 'viewReport'],
        ];

        foreach ($reportingAbilities as $ability => $handler) {
            Gate::define($ability, $handler);
        }
    }
}
