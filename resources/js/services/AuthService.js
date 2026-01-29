import { router } from '@inertiajs/vue3';
import { ToastService } from './ToastService';
import { useI18n } from '../composables/useI18n';

export class AuthService {
    /**
     * Handle user login
     * @param {Object} credentials - Login credentials
     * @param {string} credentials.email - User email
     * @param {string} credentials.password - User password
     * @param {boolean} credentials.remember - Remember user
     * @param {Object} options - Additional options
     * @param {Function} options.onStart - Callback when request starts
     * @param {Function} options.onFinish - Callback when request finishes
     * @param {Function} options.onError - Callback when request has errors
     * @param {Function} options.onSuccess - Callback when request succeeds
     */
    static login(credentials, options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;
        const { t } = useI18n();

        router.post('/login', credentials, {
            onStart: () => {
                if (onStart) onStart();
            },
            onFinish: () => {
                if (onFinish) onFinish();
            },
            onError: (errors) => {
                // Chỉ hiển thị toast cho general errors, không cho field validation errors
                if (errors.message) {
                    ToastService.error(t(errors.message)); // General error message
                } else if (Object.keys(errors).length === 0) {
                    ToastService.error(t('auth.loginError')); // Fallback error
                }
                // Field validation errors (email, password) sẽ được hiển thị dưới form
                // Không hiển thị toast để tránh duplicate

                if (onError) onError(errors);
            },
            onSuccess: (page) => {
                // Success message sẽ được hiển thị qua flash message
                if (onSuccess) onSuccess(page);
            }
        });
    }

    /**
     * Handle user logout
     */
    static logout() {
        router.post('/logout', {}, {
            // Success message sẽ được hiển thị qua flash message
        });
    }

    /**
     * Handle password reset request
     * @param {string} email - User email
     * @param {Object} options - Additional options
     */
    static forgotPassword(email, options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;
        const { t } = useI18n();

        router.post('/forgot-password', { email }, {
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
                    ToastService.error(t('common.error'));
                }
                // Field validation errors (email) sẽ được hiển thị dưới form

                if (onError) onError(errors);
            },
            onSuccess: () => {
                // Success message sẽ được hiển thị qua flash message từ backend
                // Không cần hiển thị toast ở đây để tránh duplicate
                if (onSuccess) onSuccess();
            }
        });
    }

    /**
     * Handle password reset
     * @param {Object} data - Reset password data
     * @param {string} data.token - Reset token
     * @param {string} data.email - User email
     * @param {string} data.password - New password
     * @param {string} data.password_confirmation - Password confirmation
     * @param {Object} options - Additional options
     */
    static resetPassword(data, options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;
        const { t } = useI18n();

        router.post('/reset-password', data, {
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
                    ToastService.error(t('auth.resetPasswordError'));
                }
                // Field validation errors (email, password, token) sẽ được hiển thị dưới form

                if (onError) onError(errors);
            },
            onSuccess: () => {
                ToastService.success(t('auth.passwordResetSuccess'));
                // Redirect to login after successful reset
                setTimeout(() => {
                    window.location.href = '/login';
                }, 2000);
                if (onSuccess) onSuccess();
            }
        });
    }
}
