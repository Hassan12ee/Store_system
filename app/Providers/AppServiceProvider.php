<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

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
        

        Gate::before(function ($user, $ability) {
        return $user->hasRole('Super Admin') ? true : null;
    });
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId(null);
        //
            config(['permission.models.permission' => Permission::class]);
            config(['permission.models.role' => Role::class]);
    }
}
