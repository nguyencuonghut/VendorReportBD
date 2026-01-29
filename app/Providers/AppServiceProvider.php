<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;
use App\Policies\ActivityPolicy;

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
        // Auth data is now shared via HandleInertiaRequests middleware
        // No need to share here anymore

        // Register policy for Spatie Activity model (cannot auto-discover external package models)
        Gate::policy(Activity::class, ActivityPolicy::class);
    }
}

