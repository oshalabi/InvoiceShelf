<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register container bindings.
     */
    public function register(): void
    {
        //
    }

    /**
     * Hand every Blade view the branding values it may render.
     *
     * Each one is shared under the same name as the application setting it
     * comes from, so the templates and the settings screen speak one
     * vocabulary.
     */
    public function boot(): void
    {
        $branding = [
            'login_page_logo',
            'login_page_heading',
            'login_page_description',
            'admin_page_title',
            'copyright_text',
        ];

        foreach ($branding as $setting) {
            View::share($setting, get_app_setting($setting));
        }
    }
}
