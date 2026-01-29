import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Composable for checking user roles and permissions in Vue components
 *
 * Usage:
 * const { hasRole, hasPermission, hasAnyRole, hasAllRoles, hasAnyPermission, can } = usePermission();
 *
 * if (hasRole('Super Admin')) { ... }
 * if (can('manage users')) { ... }
 *
 * In template:
 * <Button v-if="hasRole('Super Admin')" />
 * <div v-if="can('edit users')"> ... </div>
 */
export function usePermission() {
    const page = usePage();

    /**
     * Get current authenticated user
     */
    const user = computed(() => page.props.auth?.user || null);

    /**
     * Get user's roles array
     */
    const userRoles = computed(() => {
        if (!user.value) return [];
        return user.value.roles || [];
    });

    /**
     * Get user's permissions array
     */
    const userPermissions = computed(() => {
        if (!user.value) return [];
        return user.value.permissions || [];
    });

    /**
     * Check if user has a specific role
     * @param {string} role - Role name to check
     * @returns {boolean}
     */
    const hasRole = (role) => {
        if (!user.value || !role) return false;
        return userRoles.value.some(r => r.name === role);
    };

    /**
     * Check if user has any of the given roles
     * @param {string|Array<string>} roles - Role name(s) to check
     * @returns {boolean}
     */
    const hasAnyRole = (roles) => {
        if (!user.value) return false;

        const roleArray = Array.isArray(roles) ? roles : [roles];
        return roleArray.some(role => hasRole(role));
    };

    /**
     * Check if user has all of the given roles
     * @param {Array<string>} roles - Array of role names to check
     * @returns {boolean}
     */
    const hasAllRoles = (roles) => {
        if (!user.value || !Array.isArray(roles)) return false;

        return roles.every(role => hasRole(role));
    };

    /**
     * Check if user has a specific permission
     * @param {string} permission - Permission name to check
     * @returns {boolean}
     */
    const hasPermission = (permission) => {
        if (!user.value || !permission) return false;
        return userPermissions.value.some(p => p.name === permission);
    };

    /**
     * Check if user has any of the given permissions
     * @param {string|Array<string>} permissions - Permission name(s) to check
     * @returns {boolean}
     */
    const hasAnyPermission = (permissions) => {
        if (!user.value) return false;

        const permArray = Array.isArray(permissions) ? permissions : [permissions];
        return permArray.some(permission => hasPermission(permission));
    };

    /**
     * Check if user has all of the given permissions
     * @param {Array<string>} permissions - Array of permission names to check
     * @returns {boolean}
     */
    const hasAllPermissions = (permissions) => {
        if (!user.value || !Array.isArray(permissions)) return false;

        return permissions.every(permission => hasPermission(permission));
    };

    /**
     * Alias for hasPermission (Laravel-style)
     * @param {string} permission - Permission name to check
     * @returns {boolean}
     */
    const can = (permission) => {
        return hasPermission(permission);
    };

    /**
     * Check if user is Super Admin
     * @returns {boolean}
     */
    const isSuperAdmin = () => {
        return hasRole('Super Admin');
    };

    /**
     * Check if user is Admin (Super Admin or Admin)
     * @returns {boolean}
     */
    const isAdmin = () => {
        return hasAnyRole(['Super Admin', 'Admin']);
    };

    /**
     * Check if user can manage users (CRUD operations)
     * @returns {boolean}
     */
    const canManageUsers = () => {
        return isSuperAdmin();
    };

    /**
     * Check if user can manage roles (CRUD operations)
     * @returns {boolean}
     */
    const canManageRoles = () => {
        return isSuperAdmin();
    };

    /**
     * Check if user can manage backups
     * @returns {boolean}
     */
    const canManageBackups = () => {
        return isSuperAdmin();
    };

    return {
        // User data
        user,
        userRoles,
        userPermissions,

        // Role checks
        hasRole,
        hasAnyRole,
        hasAllRoles,

        // Permission checks
        hasPermission,
        hasAnyPermission,
        hasAllPermissions,
        can,

        // Convenience methods
        isSuperAdmin,
        isAdmin,
        canManageUsers,
        canManageRoles,
        canManageBackups,
    };
}
