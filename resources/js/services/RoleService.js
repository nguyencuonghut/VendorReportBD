import { router } from '@inertiajs/vue3';
import { ToastService } from './ToastService';

export class RoleService {
    /**
     * Get all roles for index page
     * @param {Object} options - Additional options
     * @param {Function} options.onStart - Callback when request starts
     * @param {Function} options.onFinish - Callback when request finishes
     * @param {Function} options.onError - Callback when request has errors
     * @param {Function} options.onSuccess - Callback when request succeeds
     */
    static index(options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;

        router.get('/roles', {}, {
            onStart: () => {
                if (onStart) onStart();
            },
            onFinish: () => {
                if (onFinish) onFinish();
            },
            onError: (errors) => {
                if (errors.message) {
                    ToastService.error(errors.message);
                } else {
                    ToastService.error('Có lỗi xảy ra khi tải danh sách vai trò!');
                }
                if (onError) onError(errors);
            },
            onSuccess: () => {
                if (onSuccess) onSuccess();
            }
        });
    }

    /**
     * Store a new role
     * @param {Object} roleData - Role data to store
     * @param {Object} options - Additional options
     */
    static store(roleData, options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;

        router.post('/roles', roleData, {
            onStart: () => {
                if (onStart) onStart();
            },
            onFinish: () => {
                if (onFinish) onFinish();
            },
            onError: (errors) => {
                // Chỉ hiển thị toast cho general errors, không cho field validation errors
                if (errors.message) {
                    ToastService.error(errors.message);
                } else if (Object.keys(errors).length === 0) {
                    ToastService.error('Có lỗi xảy ra khi tạo vai trò!');
                }
                // Field validation errors sẽ được hiển thị dưới form
                if (onError) onError(errors);
            },
            onSuccess: (page) => {
                // Success message sẽ được hiển thị qua flash message từ backend
                // Không cần hiển thị toast ở đây để tránh duplicate
                if (onSuccess) onSuccess(page);
            }
        });
    }

    /**
     * Update an existing role
     * @param {number} roleId - Role ID to update
     * @param {Object} roleData - Role data to update
     * @param {Object} options - Additional options
     */
    static update(roleId, roleData, options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;

        router.put(`/roles/${roleId}`, roleData, {
            onStart: () => {
                if (onStart) onStart();
            },
            onFinish: () => {
                if (onFinish) onFinish();
            },
            onError: (errors) => {
                // Chỉ hiển thị toast cho general errors, không cho field validation errors
                if (errors.message) {
                    ToastService.error(errors.message);
                } else if (Object.keys(errors).length === 0) {
                    ToastService.error('Có lỗi xảy ra khi cập nhật vai trò!');
                }
                // Field validation errors sẽ được hiển thị dưới form
                if (onError) onError(errors);
            },
            onSuccess: (page) => {
                // Success message sẽ được hiển thị qua flash message từ backend
                // Không cần hiển thị toast ở đây để tránh duplicate
                if (onSuccess) onSuccess(page);
            }
        });
    }

    /**
     * Delete a role
     * @param {number} roleId - Role ID to delete
     * @param {Object} options - Additional options
     */
    static destroy(roleId, options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;

        router.delete(`/roles/${roleId}`, {}, {
            onStart: () => {
                if (onStart) onStart();
            },
            onFinish: () => {
                if (onFinish) onFinish();
            },
            onError: (errors) => {
                if (errors.message) {
                    ToastService.error(errors.message);
                } else {
                    ToastService.error('Có lỗi xảy ra khi xóa vai trò!');
                }
                if (onError) onError(errors);
            },
            onSuccess: (page) => {
                // Success message sẽ được hiển thị qua flash message từ backend
                // Không cần hiển thị toast ở đây để tránh duplicate
                if (onSuccess) onSuccess(page);
            }
        });
    }

    /**
     * Delete multiple roles
     * @param {Array} roleIds - Array of role IDs to delete
     * @param {Object} options - Additional options
     */
    static bulkDelete(roleIds, options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;

        router.delete('/roles/bulk-delete', { ids: roleIds }, {
            onStart: () => {
                if (onStart) onStart();
            },
            onFinish: () => {
                if (onFinish) onFinish();
            },
            onError: (errors) => {
                if (errors.message) {
                    ToastService.error(errors.message);
                } else {
                    ToastService.error('Có lỗi xảy ra khi xóa các vai trò!');
                }
                if (onError) onError(errors);
            },
            onSuccess: (page) => {
                // Success message sẽ được hiển thị qua flash message từ backend
                // Không cần hiển thị toast ở đây để tránh duplicate
                if (onSuccess) onSuccess(page);
            }
        });
    }

    /**
     * Show a specific role
     * @param {number} roleId - Role ID to show
     * @param {Object} options - Additional options
     */
    static show(roleId, options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;

        router.get(`/roles/${roleId}`, {}, {
            onStart: () => {
                if (onStart) onStart();
            },
            onFinish: () => {
                if (onFinish) onFinish();
            },
            onError: (errors) => {
                if (errors.message) {
                    ToastService.error(errors.message);
                } else {
                    ToastService.error('Có lỗi xảy ra khi tải thông tin vai trò!');
                }
                if (onError) onError(errors);
            },
            onSuccess: () => {
                if (onSuccess) onSuccess();
            }
        });
    }
}
