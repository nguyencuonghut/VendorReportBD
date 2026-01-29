<?php

/**
 * Helper Functions for Roles & Permissions
 *
 * Các hàm helper để sử dụng trong toàn bộ ứng dụng
 */

use Illuminate\Support\Facades\Auth;
use App\Models\Activity;

if (!function_exists('hasRole')) {
    /**
     * Check if current user has a role.
     *
     * @param string|array $role
     * @return bool
     */
    function hasRole(string|array $role): bool
    {
        if (!Auth::check()) {
            return false;
        }

        return Auth::user()->hasRole($role);
    }
}

if (!function_exists('hasPermission')) {
    /**
     * Check if current user has a permission.
     *
     * @param string|array $permission
     * @return bool
     */
    function hasPermission(string|array $permission): bool
    {
        if (!Auth::check()) {
            return false;
        }

        if (is_array($permission)) {
            foreach ($permission as $perm) {
                if (Auth::user()->can($perm)) {
                    return true;
                }
            }
            return false;
        }

        return Auth::user()->can($permission);
    }
}

if (!function_exists('hasAnyRole')) {
    /**
     * Check if current user has any of the given roles.
     *
     * @param array $roles
     * @return bool
     */
    function hasAnyRole(array $roles): bool
    {
        if (!Auth::check()) {
            return false;
        }

        return Auth::user()->hasAnyRole($roles);
    }
}

if (!function_exists('hasAllRoles')) {
    /**
     * Check if current user has all of the given roles.
     *
     * @param array $roles
     * @return bool
     */
    function hasAllRoles(array $roles): bool
    {
        if (!Auth::check()) {
            return false;
        }

        return Auth::user()->hasAllRoles($roles);
    }
}

if (!function_exists('isSuperAdmin')) {
    /**
     * Check if current user is a super admin.
     *
     * @return bool
     */
    function isSuperAdmin(): bool
    {
        return hasRole('super-admin');
    }
}

if (!function_exists('isAdmin')) {
    /**
     * Check if current user is admin or super admin.
     *
     * @return bool
     */
    function isAdmin(): bool
    {
        return hasAnyRole(['super-admin', 'admin']);
    }
}

if (!function_exists('logActivity')) {
    /**
     * Log an activity.
     *
     * @param string $description
     * @param mixed|null $subject
     * @param array $properties
     * @return \App\Models\Activity
     */
    function logActivity(string $description, mixed $subject = null, array $properties = []): Activity
    {
        $activity = activity()
            ->causedBy(Auth::user())
            ->withProperties($properties)
            ->log($description);

        if ($subject) {
            $activity->performedOn($subject);
        }

        return $activity;
    }
}

if (!function_exists('getRecentActivities')) {
    /**
     * Get recent activities.
     *
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    function getRecentActivities(int $limit = 10)
    {
        return Activity::with('causer', 'subject')
            ->latest()
            ->limit($limit)
            ->get();
    }
}

if (!function_exists('getUserActivities')) {
    /**
     * Get activities for a specific user.
     *
     * @param int $userId
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    function getUserActivities(int $userId, int $limit = 10)
    {
        return Activity::with('causer', 'subject')
            ->where('causer_id', $userId)
            ->latest()
            ->limit($limit)
            ->get();
    }
}

if (!function_exists('currentUserRoles')) {
    /**
     * Get current user's role names.
     *
     * @return array
     */
    function currentUserRoles(): array
    {
        if (!Auth::check()) {
            return [];
        }

        return Auth::user()->getRoleNames()->toArray();
    }
}

if (!function_exists('currentUserPermissions')) {
    /**
     * Get current user's permission names.
     *
     * @return array
     */
    function currentUserPermissions(): array
    {
        if (!Auth::check()) {
            return [];
        }

        return Auth::user()->getAllPermissions()->pluck('name')->toArray();
    }
}

if (!function_exists('abortUnlessHasRole')) {
    /**
     * Abort unless user has role.
     *
     * @param string|array $role
     * @param int $code
     * @param string $message
     * @return void
     */
    function abortUnlessHasRole(string|array $role, int $code = 403, string $message = 'Unauthorized action.')
    {
        if (!hasRole($role)) {
            abort($code, $message);
        }
    }
}

if (!function_exists('abortUnlessHasPermission')) {
    /**
     * Abort unless user has permission.
     *
     * @param string|array $permission
     * @param int $code
     * @param string $message
     * @return void
     */
    function abortUnlessHasPermission(string|array $permission, int $code = 403, string $message = 'Unauthorized action.')
    {
        if (!hasPermission($permission)) {
            abort($code, $message);
        }
    }
}
