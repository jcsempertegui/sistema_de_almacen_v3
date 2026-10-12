<?php

namespace App\Providers;

use App\Models\Role;
use App\Models\User;
use App\Models\Worker;
use App\Policies\UserPolicy;
use App\Policies\WorkerPolicy;
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
        Gate::define('access-panel', fn (User $user) => $user->hasRole(...array_keys(Role::LABELS)));
    }
}
