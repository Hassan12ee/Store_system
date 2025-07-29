<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId(null);
        //
            config(['permission.models.permission' => Permission::class]);
            config(['permission.models.role' => Role::class]);
    }
}
