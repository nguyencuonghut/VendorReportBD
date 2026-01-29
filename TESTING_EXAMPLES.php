<?php

/**
 * EXAMPLES - Testing Roles & Permissions
 *
 * Chạy các examples này trong php artisan tinker
 * để test roles & permissions system
 */

// ==========================================
// 1. TEST USER ROLES
// ==========================================

// Get first user
$user = App\Models\User::first();

// Check if user has role
$user->hasRole('super-admin'); // true or false

// Get user's roles
$user->getRoleNames(); // Collection of role names

// Get user's roles with permissions
$user->roles()->with('permissions')->get();

// Check if user has any role
$user->hasAnyRole(['admin', 'super-admin']); // true if has any

// Check if user has all roles
$user->hasAllRoles(['admin', 'manager']); // true if has all

// Assign role to user
$user->assignRole('admin');

// Assign multiple roles
$user->assignRole(['admin', 'manager']);

// Remove role from user
$user->removeRole('admin');

// Sync roles (remove all and assign new)
$user->syncRoles(['admin', 'manager']);


// ==========================================
// 2. TEST USER PERMISSIONS
// ==========================================

// Check if user has permission
$user->can('edit users'); // true or false

// Check if user has any permission
$user->hasAnyPermission(['edit users', 'delete users']);

// Check if user has all permissions
$user->hasAllPermissions(['edit users', 'delete users']);

// Get all user's permissions (including via roles)
$user->getAllPermissions();

// Get permission names
$user->getAllPermissions()->pluck('name');

// Give permission directly to user
$user->givePermissionTo('edit users');

// Give multiple permissions
$user->givePermissionTo(['edit users', 'delete users']);

// Revoke permission
$user->revokePermissionTo('edit users');

// Sync permissions
$user->syncPermissions(['edit users', 'delete users']);


// ==========================================
// 3. TEST ROLES
// ==========================================

// Get all roles
App\Models\Role::all();

// Get role by name
$role = App\Models\Role::findByName('admin');

// Get role with permissions
$role = App\Models\Role::with('permissions')->findByName('admin');

// Get role with users
$role = App\Models\Role::with('users')->findByName('admin');

// Count users with role
$role->users()->count();

// Create new role
$newRole = App\Models\Role::create(['name' => 'teacher']);

// Give permissions to role
$role->givePermissionTo('view users');
$role->givePermissionTo(['view users', 'edit users']);

// Sync permissions for role
$role->syncPermissions(['view users', 'edit users', 'view activity logs']);

// Delete role
$role->delete();


// ==========================================
// 4. TEST PERMISSIONS
// ==========================================

// Get all permissions
App\Models\Permission::all();

// Get permission by name
$permission = App\Models\Permission::findByName('edit users');

// Create new permission
$newPerm = App\Models\Permission::create(['name' => 'manage courses']);

// Get all roles that have this permission
$permission->roles()->get();

// Get all users that have this permission
$permission->users()->get();

// Delete permission
$permission->delete();


// ==========================================
// 5. TEST ACTIVITY LOGS
// ==========================================

// Get all activities
App\Models\Activity::all();

// Get recent activities (10 latest)
App\Models\Activity::latest()->limit(10)->get();

// Get activities with causer and subject
App\Models\Activity::with('causer', 'subject')->latest()->get();

// Get activities by specific user
App\Models\Activity::where('causer_id', 1)->get();
// Or using relationship
App\Models\Activity::causedBy(App\Models\User::find(1))->get();

// Get activities for specific subject (model)
App\Models\Activity::forSubject($user)->get();

// Log new activity manually
activity()
    ->causedBy(App\Models\User::find(1))
    ->log('User logged in manually');

// Log activity on a model
activity()
    ->performedOn($user)
    ->causedBy(App\Models\User::find(1))
    ->withProperties(['key' => 'value'])
    ->log('Updated user profile');

// Clear all activities
App\Models\Activity::truncate();

// Delete old activities (older than 30 days)
App\Models\Activity::where('created_at', '<', now()->subDays(30))->delete();


// ==========================================
// 6. TEST HELPER FUNCTIONS
// ==========================================

// Must be logged in first
Auth::login(App\Models\User::first());

// Check role
hasRole('admin'); // true or false
hasAnyRole(['admin', 'manager']); // true or false
hasAllRoles(['admin', 'manager']); // true or false

// Check permission
hasPermission('edit users'); // true or false

// Check if super admin
isSuperAdmin(); // true or false

// Check if admin
isAdmin(); // true or false (admin or super-admin)

// Get current user roles
currentUserRoles(); // ['admin', 'manager']

// Get current user permissions
currentUserPermissions(); // ['edit users', 'delete users', ...]

// Log activity quickly
logActivity('User viewed dashboard');

// Get recent activities
getRecentActivities(5);

// Get user's activities
getUserActivities(1, 10);


// ==========================================
// 7. COMPLEX QUERIES
// ==========================================

// Get all users with specific role
App\Models\User::role('admin')->get();

// Get all users with specific permission
App\Models\User::permission('edit users')->get();

// Get users without any role
App\Models\User::doesntHave('roles')->get();

// Get users with multiple roles
App\Models\User::role(['admin', 'manager'])->get();

// Count users per role
App\Models\Role::withCount('users')->get();

// Get most active users (by activity log)
App\Models\User::withCount('actions')->orderBy('actions_count', 'desc')->take(10)->get();


// ==========================================
// 8. SEED MORE DATA
// ==========================================

// Create new permissions for a module
$coursePermissions = ['view courses', 'create courses', 'edit courses', 'delete courses'];
foreach ($coursePermissions as $perm) {
    App\Models\Permission::create(['name' => $perm]);
}

// Create teacher role and assign permissions
$teacher = App\Models\Role::create(['name' => 'teacher']);
$teacher->givePermissionTo(['view courses', 'create courses', 'edit courses', 'view users']);

// Assign teacher role to users
App\Models\User::whereBetween('id', [5, 10])->each(function ($user) {
    $user->assignRole('teacher');
});


// ==========================================
// 9. CHECK CACHE
// ==========================================

// Clear permission cache
app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
// Or
Artisan::call('permission:cache-reset');

// Get cache key
config('permission.cache.key'); // 'spatie.permission.cache'


// ==========================================
// 10. QUICK TESTS
// ==========================================

// Test 1: Create user and assign role
$testUser = App\Models\User::factory()->create([
    'name' => 'Test User',
    'email' => 'test@example.com',
]);
$testUser->assignRole('user');
$testUser->hasRole('user'); // Should return true

// Test 2: Check permissions through role
$admin = App\Models\User::role('admin')->first();
$admin->can('view users'); // Should return true
$admin->can('delete users'); // Should return false (admin doesn't have this)

// Test 3: Activity log auto-tracking
$user = App\Models\User::first();
$oldName = $user->name;
$user->update(['name' => 'New Name']);
// Check latest activity
App\Models\Activity::latest()->first(); // Should log the update

// Test 4: Direct permission assignment
$user = App\Models\User::find(1);
$user->givePermissionTo('special permission'); // Even if not in role
$user->can('special permission'); // Should return true

// Clean up
$testUser->delete();


// ==========================================
// 11. DEBUGGING
// ==========================================

// See all permissions for a user
$user = App\Models\User::find(1);
echo "User: " . $user->name . "\n";
echo "Roles: " . $user->getRoleNames()->implode(', ') . "\n";
echo "Permissions: " . $user->getAllPermissions()->pluck('name')->implode(', ') . "\n";

// See all roles and their permissions
App\Models\Role::with('permissions')->get()->each(function ($role) {
    echo "\nRole: " . $role->name . "\n";
    echo "Permissions: " . $role->permissions->pluck('name')->implode(', ') . "\n";
});

// See activity log summary
echo "\nTotal activities: " . App\Models\Activity::count() . "\n";
echo "Activities today: " . App\Models\Activity::whereDate('created_at', today())->count() . "\n";
echo "Unique users: " . App\Models\Activity::distinct('causer_id')->count('causer_id') . "\n";


// ==========================================
// NOTES:
// ==========================================
/*
 * - Run these in tinker: php artisan tinker
 * - Use Auth::login($user) to test as specific user
 * - Cache is cleared automatically when roles/permissions change
 * - Activity logs are created automatically for models with LogsActivity trait
 * - You can disable activity logging: config(['activitylog.enabled' => false])
 */
