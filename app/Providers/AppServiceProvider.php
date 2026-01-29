<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;
use App\Policies\ActivityPolicy;
use App\Models\VendorReport;
use App\Policies\VendorReportPolicy;
use App\Models\Department;
use App\Policies\DepartmentPolicy;

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

        // Register policies for models (auto-discovery or explicit)
        Gate::policy(Activity::class, ActivityPolicy::class);
        Gate::policy(VendorReport::class, VendorReportPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
    }
}

