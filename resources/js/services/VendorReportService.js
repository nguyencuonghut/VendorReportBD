import { router } from '@inertiajs/vue3';
import { ToastService } from './ToastService';

export class VendorReportService {
    /**
     * Get all vendor reports for index page
     * @param {Object} filters - Filter parameters (status, workflow_type, department_id, search, etc.)
     * @param {Object} options - Additional options
     */
    static index(filters = {}, options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;

        router.get('/vendor-reports', filters, {
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
                    ToastService.error('Có lỗi xảy ra khi tải danh sách phiếu!');
                }
                if (onError) onError(errors);
            },
            onSuccess: () => {
                if (onSuccess) onSuccess();
            }
        });
    }

    /**
     * Store a new vendor report
     * @param {Object} reportData - Vendor report data to store
     * @param {Object} options - Additional options
     */
    static store(reportData, options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;

        router.post('/vendor-reports', reportData, {
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
                    ToastService.error('Có lỗi xảy ra khi tạo phiếu!');
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
     * Update an existing vendor report
     * @param {number} reportId - Vendor report ID to update
     * @param {Object} reportData - Vendor report data to update
     * @param {Object} options - Additional options
     */
    static update(reportId, reportData, options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;

        router.put(`/vendor-reports/${reportId}`, reportData, {
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
                    ToastService.error('Có lỗi xảy ra khi cập nhật phiếu!');
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
     * Delete a vendor report
     * @param {number} reportId - Vendor report ID to delete
     * @param {Object} options - Additional options
     */
    static destroy(reportId, options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;

        router.delete(`/vendor-reports/${reportId}`, {
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
                    ToastService.error('Có lỗi xảy ra khi xóa phiếu!');
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
     * Show vendor report detail
     * @param {number} reportId - Vendor report ID to show
     * @param {Object} options - Additional options
     */
    static show(reportId, options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;

        router.get(`/vendor-reports/${reportId}`, {}, {
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
                    ToastService.error('Có lỗi xảy ra khi tải thông tin phiếu!');
                }
                if (onError) onError(errors);
            },
            onSuccess: () => {
                if (onSuccess) onSuccess();
            }
        });
    }

    /**
     * Submit a vendor report for approval
     * @param {number} reportId - Vendor report ID to submit
     * @param {Object} options - Additional options
     */
    static submit(reportId, options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;

        router.post(`/vendor-reports/${reportId}/submit`, {}, {
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
                    ToastService.error('Có lỗi xảy ra khi nộp phiếu!');
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
     * Approve a vendor report
     * @param {number} reportId - Vendor report ID to approve
     * @param {Object} approvalData - Approval data (note, selected_next_approver_id)
     * @param {Object} options - Additional options
     */
    static approve(reportId, approvalData, options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;

        router.post(`/vendor-reports/${reportId}/approve`, approvalData, {
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
                    ToastService.error('Có lỗi xảy ra khi phê duyệt phiếu!');
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
     * Reject a vendor report
     * @param {number} reportId - Vendor report ID to reject
     * @param {Object} rejectionData - Rejection data (rejection_note)
     * @param {Object} options - Additional options
     */
    static reject(reportId, rejectionData, options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;

        router.post(`/vendor-reports/${reportId}/reject`, rejectionData, {
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
                    ToastService.error('Có lỗi xảy ra khi từ chối phiếu!');
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
     * Clone a vendor report
     * @param {number} reportId - Vendor report ID to clone
     * @param {Object} options - Additional options
     */
    static clone(reportId, options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;

        router.post(`/vendor-reports/${reportId}/clone`, {}, {
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
                    ToastService.error('Có lỗi xảy ra khi sao chép phiếu!');
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
