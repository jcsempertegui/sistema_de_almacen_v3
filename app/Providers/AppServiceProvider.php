<?php

namespace App\Providers;

use App\Models\AdministrativeAuditLog;
use App\Models\Category;
use App\Models\MeasurementUnit;
use App\Models\Role;
use App\Models\User;
use App\Models\Worker;
use App\Policies\AdministrativeAuditLogPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\MeasurementUnitPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use App\Policies\WorkerPolicy;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Worker::class, WorkerPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(AdministrativeAuditLog::class, AdministrativeAuditLogPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(MeasurementUnit::class, MeasurementUnitPolicy::class);
        Gate::define('access-panel', fn (User $user) => $user->hasPermission('panel.view'));
        foreach (array_keys(PermissionCatalog::LABELS) as $code) {
            Gate::define($code, fn (User $user) => $user->hasPermission($code));
        }
    }
}
