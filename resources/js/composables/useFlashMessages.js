import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ToastService } from '../services/ToastService';
import { useI18n } from './useI18n';

export function useFlashMessages() {
    const page = usePage();
    const { t } = useI18n();

    // Modern message + type pattern
    const flashMessage = computed(() => page.props.flash?.message);
    const flashType = computed(() => page.props.flash?.type);

    // Legacy flash messages (for backward compatibility)
    const flashSuccess = computed(() => page.props.flash?.success);
    const flashError = computed(() => page.props.flash?.error);

    // Handle flash messages with intelligent display logic
    const handleFlashMessages = () => {
        // Handle modern message + type pattern
        if (flashMessage.value && flashType.value) {
            const translatedMessage = t(flashMessage.value);

            switch (flashType.value) {
                case 'success':
                    ToastService.success(translatedMessage);
                    break;
                case 'error':
                    ToastService.error(translatedMessage);
                    break;
                case 'warning':
                    ToastService.warn(translatedMessage);
                    break;
                case 'info':
                    ToastService.info(translatedMessage);
                    break;
                default:
                    ToastService.info(translatedMessage);
            }
        }

        // Handle legacy flash messages (deprecated but maintained for compatibility)
        if (flashSuccess.value) {
            ToastService.success(t(flashSuccess.value));
        }

        if (flashError.value) {
            ToastService.error(t(flashError.value));
        }
    };    return {
        // Modern API
        flashMessage,
        flashType,

        // Legacy API (deprecated)
        flashSuccess,
        flashError,

        handleFlashMessages
    };
}
