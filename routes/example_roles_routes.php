<?php

/**
 * EXAMPLE ROUTES FOR ROLES & PERMISSIONS
 *
 * Copy các routes này vào routes/web.php của bạn
 * và chỉnh sửa theo nhu cầu
 */

use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ActivityLogController;
use Illuminate\Support\Facades\Route;

// Routes yêu cầu authentication
Route::middleware(['auth'])->group(function () {

    // ==========================================
    // ROLES MANAGEMENT
    // ==========================================
    Route::middleware('permission:view roles')->group(function () {
        // List all roles
        Route::get('/roles', [RoleController::class, 'index'])
            ->name('roles.index');

        // Show single role
        Route::get('/roles/{role}', [RoleController::class, 'show'])
            ->name('roles.show');
    });

    Route::middleware('permission:create roles')->group(function () {
        // Show create form
        Route::get('/roles/create', [RoleController::class, 'create'])
            ->name('roles.create');

        // Store new role
        Route::post('/roles', [RoleController::class, 'store'])
            ->name('roles.store');
    });

    Route::middleware('permission:edit roles')->group(function () {
        // Show edit form
        Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])
            ->name('roles.edit');

        // Update role
        Route::put('/roles/{role}', [RoleController::class, 'update'])
            ->name('roles.update');
    });

    Route::middleware('permission:delete roles')->group(function () {
        // Delete role
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])
            ->name('roles.destroy');
    });

    // Hoặc sử dụng resource route (đơn giản hơn):
    // Route::middleware('permission:view roles')->group(function () {
    //     Route::resource('roles', RoleController::class);
    // });


    // ==========================================
    // PERMISSIONS MANAGEMENT (optional)
    // ==========================================
    Route::middleware('permission:view permissions')->group(function () {
        Route::get('/permissions', [PermissionController::class, 'index'])
            ->name('permissions.index');

        Route::get('/permissions/{permission}', [PermissionController::class, 'show'])
            ->name('permissions.show');
    });

    Route::middleware('permission:assign permissions')->group(function () {
        Route::post('/users/{user}/assign-role', [UserController::class, 'assignRole'])
            ->name('users.assign-role');

        Route::post('/users/{user}/assign-permission', [UserController::class, 'assignPermission'])
            ->name('users.assign-permission');

        Route::post('/roles/{role}/assign-permission', [RoleController::class, 'assignPermission'])
            ->name('roles.assign-permission');
    });


    // ==========================================
    // ACTIVITY LOGS
    // ==========================================
    Route::middleware('permission:view activity logs')->group(function () {
        // List all activity logs
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])
            ->name('activity-logs.index');

        // Show single activity log
        Route::get('/activity-logs/{activity}', [ActivityLogController::class, 'show'])
            ->name('activity-logs.show');
    });

    Route::middleware('permission:delete activity logs')->group(function () {
        // Delete single activity log
        Route::delete('/activity-logs/{activity}', [ActivityLogController::class, 'destroy'])
            ->name('activity-logs.destroy');

        // Clear all activity logs
        Route::post('/activity-logs/clear', [ActivityLogController::class, 'clear'])
            ->name('activity-logs.clear');
    });


    // ==========================================
    // ADMIN DASHBOARD (example)
    // ==========================================
    Route::middleware('role:super-admin|admin')->group(function () {
        Route::get('/admin/dashboard', function () {
            return Inertia::render('Admin/Dashboard');
        })->name('admin.dashboard');

        Route::get('/admin/users', [AdminController::class, 'users'])
            ->name('admin.users');
    });


    // ==========================================
    // USER MANAGEMENT WITH ROLES (example)
    // ==========================================
    Route::middleware('permission:view users')->group(function () {
        Route::get('/users', [UserController::class, 'index'])
            ->name('users.index');
    });

    Route::middleware('permission:edit users')->group(function () {
        Route::put('/users/{user}', [UserController::class, 'update'])
            ->name('users.update');
    });

    Route::middleware('permission:delete users')->group(function () {
        Route::delete('/users/{user}', [UserController::class, 'destroy'])
            ->name('users.destroy');
    });


    // ==========================================
    // BACKUP MANAGEMENT WITH PERMISSIONS
    // ==========================================
    Route::middleware('permission:view backups')->group(function () {
        Route::get('/backups', [BackupController::class, 'index'])
            ->name('backups.index');
    });

    Route::middleware('permission:create backups')->group(function () {
        Route::post('/backups', [BackupController::class, 'store'])
            ->name('backups.store');
    });

    Route::middleware('permission:delete backups')->group(function () {
        Route::delete('/backups/{backup}', [BackupController::class, 'destroy'])
            ->name('backups.destroy');
    });

    Route::middleware('permission:configure backups')->group(function () {
        Route::get('/backup-configurations', [BackupConfigurationController::class, 'index'])
            ->name('backup-configurations.index');

        Route::post('/backup-configurations', [BackupConfigurationController::class, 'store'])
            ->name('backup-configurations.store');
    });


    // ==========================================
    // API ROUTES WITH PERMISSIONS (example)
    // ==========================================
    Route::prefix('api')->group(function () {
        Route::middleware('permission:view users')->group(function () {
            Route::get('/users', [Api\UserController::class, 'index']);
        });

        Route::middleware('permission:view activity logs')->group(function () {
            Route::get('/activity-logs', [Api\ActivityLogController::class, 'index']);
        });
    });
});


// ==========================================
// ALTERNATIVE: Using Route Groups by Role
// ==========================================

// Super Admin Only Routes
Route::middleware(['auth', 'role:super-admin'])->prefix('super-admin')->group(function () {
    Route::get('/dashboard', [SuperAdminController::class, 'dashboard'])
        ->name('super-admin.dashboard');

    Route::resource('roles', RoleController::class);
    Route::resource('permissions', PermissionController::class);
});

// Admin Routes (Super Admin & Admin)
Route::middleware(['auth', 'role:super-admin|admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])
        ->name('admin.dashboard');

    Route::resource('users', UserController::class);
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])
        ->name('admin.activity-logs');
});

// Manager Routes
Route::middleware(['auth', 'role:super-admin|admin|manager'])->prefix('manager')->group(function () {
    Route::get('/dashboard', [ManagerController::class, 'dashboard'])
        ->name('manager.dashboard');

    Route::get('/backups', [BackupController::class, 'index'])
        ->name('manager.backups');
});

// User Routes (All authenticated users)
Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])
        ->name('profile.show');

    Route::put('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');
});


// ==========================================
// CHECKING MULTIPLE PERMISSIONS (AND logic)
// ==========================================

// User must have ALL permissions
Route::middleware(['auth', 'permission:view users,edit users,delete users'])->group(function () {
    Route::get('/user-management', [UserManagementController::class, 'index']);
});


// ==========================================
// NOTES & BEST PRACTICES
// ==========================================

/*
 * 1. Permission Check vs Role Check:
 *    - Use 'permission' middleware when you care about WHAT user can do
 *    - Use 'role' middleware when you care about WHO user is
 *
 * 2. Prefer Permissions over Roles in routes:
 *    - More flexible
 *    - Easier to change
 *    - Better for complex systems
 *
 * 3. Always check permissions in Controller as well:
 *    - Don't rely only on middleware
 *    - Add explicit checks in methods
 *
 *    Example:
 *    public function destroy(User $user) {
 *        if (!Auth::user()->can('delete users')) {
 *            abort(403);
 *        }
 *        // ... delete logic
 *    }
 *
 * 4. Use Policies for complex authorization logic:
 *    php artisan make:policy UserPolicy --model=User
 *
 * 5. Log important actions:
 *    activity()
 *        ->performedOn($model)
 *        ->causedBy(Auth::user())
 *        ->log('Action description');
 */
