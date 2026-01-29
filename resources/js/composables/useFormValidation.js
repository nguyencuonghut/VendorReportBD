import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useFormValidation() {
    const page = usePage();
    const processing = ref(false);

    // Get validation errors from Inertia
    const errors = computed(() => page.props.errors || {});

    // Check if form has any errors
    const hasErrors = computed(() => Object.keys(errors.value).length > 0);

    // Get error for specific field
    const getError = (field) => {
        return errors.value[field] || null;
    };

    // Check if specific field has error
    const hasError = (field) => {
        return !!errors.value[field];
    };

    // Set processing state
    const setProcessing = (state) => {
        processing.value = state;
    };

    return {
        errors,
        hasErrors,
        processing,
        getError,
        hasError,
        setProcessing
    };
}
