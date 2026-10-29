<?php

namespace App\Platform\Mail;

use App\Platform\Mail\Application\EloquentEmailLogWriter;
use App\Platform\Mail\Application\MailConfigurationService;
use App\Platform\Mail\Contracts\EmailLogWriter;
use App\Platform\Mail\Contracts\MailConfigurator;
use App\Platform\Mail\Policies\MailAccessPolicy;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class MailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MailConfigurationService::class);
        $this->app->bind(
            MailConfigurator::class,
            fn (Application $app): MailConfigurator => $app->make(MailConfigurationService::class),
        );
        $this->app->bind(EmailLogWriter::class, EloquentEmailLogWriter::class);
    }

    public function boot(): void
    {
        Gate::define('manage email config', [MailAccessPolicy::class, 'manageConfiguration']);
    }
}
