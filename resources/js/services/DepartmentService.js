import { router } from '@inertiajs/vue3';
import { ToastService } from './ToastService';

export class DepartmentService {
    /**
     * Get all departments for index page
     * @param {Object} filters - Filter parameters (search, is_active)
     * @param {Object} options - Additional options
     */
    static index(filters = {}, options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;

        router.get('/departments', filters, {
            preserveState: true,
            preserveScroll: true,
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
                    ToastService.error('Có lỗi xảy ra khi tải danh sách phòng ban!');
                }
                if (onError) onError(errors);
            },
            onSuccess: () => {
                if (onSuccess) onSuccess();
            }
        });
    }

    /**
     * Store a new department
     * @param {Object} departmentData - Department data to store
     * @param {Object} options - Additional options
     */
    static store(departmentData, options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;

        router.post('/departments', departmentData, {
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
                    ToastService.error('Có lỗi xảy ra khi tạo phòng ban!');
                }
                // Field validation errors sẽ được hiển thị dưới form
                if (onError) onError(errors);
            },
            onSuccess: (page) => {
                // Success message sẽ được hiển thị qua flash message từ backend
                if (onSuccess) onSuccess(page);
            }
        });
    }

    /**
     * Update an existing department
     * @param {number} departmentId - Department ID to update
     * @param {Object} departmentData - Department data to update
     * @param {Object} options - Additional options
     */
    static update(departmentId, departmentData, options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;

        router.put(`/departments/${departmentId}`, departmentData, {
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
                    ToastService.error('Có lỗi xảy ra khi cập nhật phòng ban!');
                }
                // Field validation errors sẽ được hiển thị dưới form
                if (onError) onError(errors);
            },
            onSuccess: (page) => {
                // Success message sẽ được hiển thị qua flash message từ backend
                if (onSuccess) onSuccess(page);
            }
        });
    }

    /**
     * Delete (deactivate) a department
     * @param {number} departmentId - Department ID to delete
     * @param {Object} options - Additional options
     */
    static destroy(departmentId, options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;

        router.delete(`/departments/${departmentId}`, {
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
                    ToastService.error('Có lỗi xảy ra khi xóa phòng ban!');
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
     * Show department detail
     * @param {number} departmentId - Department ID to show
     * @param {Object} options - Additional options
     */
    static show(departmentId, options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;

        router.get(`/departments/${departmentId}`, {}, {
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
                    ToastService.error('Có lỗi xảy ra khi tải thông tin phòng ban!');
                }
                if (onError) onError(errors);
            },
            onSuccess: () => {
                if (onSuccess) onSuccess();
            }
        });
    }
}
