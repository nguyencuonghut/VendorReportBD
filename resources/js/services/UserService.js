import { router } from '@inertiajs/vue3';
import { ToastService } from './ToastService';
import { useI18n } from '../composables/useI18n';

export class UserService {
    /**
     * Get all users for index page
     * @param {Object} options - Additional options
     * @param {Function} options.onStart - Callback when request starts
     * @param {Function} options.onFinish - Callback when request finishes
     * @param {Function} options.onError - Callback when request has errors
     * @param {Function} options.onSuccess - Callback when request succeeds
     */
    static index(options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;
        const { t } = useI18n();

        router.get('/users', {}, {
            onStart: () => {
                if (onStart) onStart();
            },
            onFinish: () => {
                if (onFinish) onFinish();
            },
            onError: (errors) => {
                if (errors.message) {
                    ToastService.error(t(errors.message));
                } else {
                    ToastService.error(t('users.loadError'));
                }
                if (onError) onError(errors);
            },
            onSuccess: () => {
                if (onSuccess) onSuccess();
            }
        });
    }

    /**
     * Store a new user
     * @param {Object} userData - User data to store
     * @param {Object} options - Additional options
     */
    static store(userData, options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;
        const { t } = useI18n();

        router.post('/users', userData, {
            onStart: () => {
                if (onStart) onStart();
            },
            onFinish: () => {
                if (onFinish) onFinish();
            },
            onError: (errors) => {
                // Chỉ hiển thị toast cho general errors, không cho field validation errors
                if (errors.message) {
                    ToastService.error(t(errors.message));
                } else if (Object.keys(errors).length === 0) {
                    ToastService.error(t('users.createError'));
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
     * Update an existing user
     * @param {number} userId - User ID to update
     * @param {Object} userData - User data to update
     * @param {Object} options - Additional options
     */
    static update(userId, userData, options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;
        const { t } = useI18n();

        router.put(`/users/${userId}`, userData, {
            onStart: () => {
                if (onStart) onStart();
            },
            onFinish: () => {
                if (onFinish) onFinish();
            },
            onError: (errors) => {
                // Chỉ hiển thị toast cho general errors, không cho field validation errors
                if (errors.message) {
                    ToastService.error(t(errors.message));
                } else if (Object.keys(errors).length === 0) {
                    ToastService.error(t('users.updateError'));
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
     * Delete a user
     * @param {number} userId - User ID to delete
     * @param {Object} options - Additional options
     */
    static destroy(userId, options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;
        const { t } = useI18n();

        router.delete(`/users/${userId}`, {
            onStart: () => {
                if (onStart) onStart();
            },
            onFinish: () => {
                if (onFinish) onFinish();
            },
            onError: (errors) => {
                if (errors.message) {
                    ToastService.error(t(errors.message));
                } else {
                    ToastService.error(t('users.deleteError'));
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
     * Delete multiple users
     * @param {Array} userIds - Array of user IDs to delete
     * @param {Object} options - Additional options
     */
    static bulkDelete(userIds, options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;
        const { t } = useI18n();

        router.delete('/users/bulk-delete', {
            data: {
                ids: userIds, // Gửi mảng IDs
            },
            onStart: () => {
                if (onStart) onStart();
            },
            onFinish: () => {
                if (onFinish) onFinish();
            },
            onError: (errors) => {
                if (errors.message) {
                    ToastService.error(t(errors.message));
                } else {
                    ToastService.error(t('users.bulkDeleteError'));
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
     * Show a specific user
     * @param {number} userId - User ID to show
     * @param {Object} options - Additional options
     */
    static show(userId, options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;
        const { t } = useI18n();

        router.get(`/users/${userId}`, {}, {
            onStart: () => {
                if (onStart) onStart();
            },
            onFinish: () => {
                if (onFinish) onFinish();
            },
            onError: (errors) => {
                if (errors.message) {
                    ToastService.error(t(errors.message));
                } else {
                    ToastService.error(t('users.loadError'));
                }
                if (onError) onError(errors);
            },
            onSuccess: () => {
                if (onSuccess) onSuccess();
            }
        });
    }

    /**
     * Restore a soft deleted user
     * @param {number} userId - User ID to restore
     * @param {Object} options - Additional options
     */
    static restore(userId, options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;
        const { t } = useI18n();

        router.patch(`/users/${userId}/restore`, {}, {
            onStart: () => {
                if (onStart) onStart();
            },
            onFinish: () => {
                if (onFinish) onFinish();
            },
            onError: (errors) => {
                if (errors.message) {
                    ToastService.error(t(errors.message));
                } else {
                    ToastService.error(t('users.restoreError'));
                }
                if (onError) onError(errors);
            },
            onSuccess: (page) => {
                // Success message sẽ được hiển thị qua flash message từ backend
                if (onSuccess) onSuccess(page);
            }
        });
    }

    /**
     * Permanently delete a user (force delete)
     * @param {number} userId - User ID to permanently delete
     * @param {Object} options - Additional options
     */
    static forceDelete(userId, options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;
        const { t } = useI18n();

        router.delete(`/users/${userId}/force`, {
            onStart: () => {
                if (onStart) onStart();
            },
            onFinish: () => {
                if (onFinish) onFinish();
            },
            onError: (errors) => {
                if (errors.message) {
                    ToastService.error(t(errors.message));
                } else {
                    ToastService.error(t('users.forceDeleteError'));
                }
                if (onError) onError(errors);
            },
            onSuccess: (page) => {
                // Success message sẽ được hiển thị qua flash message từ backend
                if (onSuccess) onSuccess(page);
            }
        });
    }
}
