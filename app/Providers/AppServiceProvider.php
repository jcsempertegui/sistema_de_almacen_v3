<?php

namespace App\Providers;

use App\Models\AdministrativeAuditLog;
use App\Models\CatalogAttribute;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\IndividualUnit;
use App\Models\Location;
use App\Models\MeasurementUnit;
use App\Models\Product;
use App\Models\ProductLocation;
use App\Models\Role;
use App\Models\User;
use App\Models\Worker;
use App\Policies\AdministrativeAuditLogPolicy;
use App\Policies\CatalogAttributePolicy;
use App\Policies\CategoryAttributePolicy;
use App\Policies\CategoryPolicy;
use App\Policies\IndividualUnitPolicy;
use App\Policies\LocationPolicy;
use App\Policies\MeasurementUnitPolicy;
use App\Policies\ProductLocationPolicy;
use App\Policies\ProductPolicy;
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
        Gate::policy(IndividualUnit::class, IndividualUnitPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(ProductLocation::class, ProductLocationPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Worker::class, WorkerPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(AdministrativeAuditLog::class, AdministrativeAuditLogPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(CatalogAttribute::class, CatalogAttributePolicy::class);
        Gate::policy(CategoryAttribute::class, CategoryAttributePolicy::class);
        Gate::policy(MeasurementUnit::class, MeasurementUnitPolicy::class);
        Gate::policy(Location::class, LocationPolicy::class);
        Gate::define('access-panel', fn (User $user) => $user->hasPermission('panel.view'));
        foreach (array_keys(PermissionCatalog::LABELS) as $code) {
            Gate::define($code, fn (User $user) => $user->hasPermission($code));
        }
    }
}
