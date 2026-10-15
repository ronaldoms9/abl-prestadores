<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class PermissionProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
                //
        Blade::if('permission', function ($permissions, $full = false) {
            return auth()->check() && auth()->user()->can($permissions, $full);
        });
    }
}
