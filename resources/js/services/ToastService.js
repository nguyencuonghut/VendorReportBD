import { useToast } from 'primevue/usetoast';
import { useI18n } from '../composables/useI18n';

export class ToastService {
    static toast = null;
    static i18n = null;

    /**
     * Initialize toast service
     * @param {Object} toastInstance - Toast instance from useToast()
     */
    static init(toastInstance) {
        this.toast = toastInstance;
        this.i18n = useI18n();
    }

    /**
     * Show success toast
     * @param {string} message - Success message
     * @param {string} title - Toast title (optional, will use i18n if not provided)
     * @param {number} life - Toast life in milliseconds
     */
    static success(message, title = null, life = 3000) {
        if (!this.toast) {
            console.warn('ToastService not initialized');
            return;
        }

        const toastTitle = title || (this.i18n ? this.i18n.t('common.success') : 'Thành công');

        this.toast.add({
            severity: 'success',
            summary: toastTitle,
            detail: message,
            life: life
        });
    }

    /**
     * Show error toast
     * @param {string} message - Error message
     * @param {string} title - Toast title (optional, will use i18n if not provided)
     * @param {number} life - Toast life in milliseconds
     */
    static error(message, title = null, life = 5000) {
        if (!this.toast) {
            console.warn('ToastService not initialized');
            return;
        }

        const toastTitle = title || (this.i18n ? this.i18n.t('common.error') : 'Lỗi');

        this.toast.add({
            severity: 'error',
            summary: toastTitle,
            detail: message,
            life: life
        });
    }

    /**
     * Show warning toast
     * @param {string} message - Warning message
     * @param {string} title - Toast title (optional, will use i18n if not provided)
     * @param {number} life - Toast life in milliseconds
     */
    static warn(message, title = null, life = 4000) {
        if (!this.toast) {
            console.warn('ToastService not initialized');
            return;
        }

        const toastTitle = title || (this.i18n ? this.i18n.t('common.warning') : 'Cảnh báo');

        this.toast.add({
            severity: 'warn',
            summary: toastTitle,
            detail: message,
            life: life
        });
    }

    /**
     * Show info toast
     * @param {string} message - Info message
     * @param {string} title - Toast title (optional, will use i18n if not provided)
     * @param {number} life - Toast life in milliseconds
     */
    static info(message, title = null, life = 3000) {
        if (!this.toast) {
            console.warn('ToastService not initialized');
            return;
        }

        const toastTitle = title || (this.i18n ? this.i18n.t('common.info') : 'Thông tin');

        this.toast.add({
            severity: 'info',
            summary: toastTitle,
            detail: message,
            life: life
        });
    }

    /**
     * Clear all toasts
     */
    static clear() {
        if (!this.toast) {
            console.warn('ToastService not initialized');
            return;
        }

        this.toast.removeAllGroups();
    }
}
