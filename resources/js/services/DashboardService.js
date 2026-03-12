import axios from 'axios';
import { ToastService } from './ToastService';

export class DashboardService {
    /**
     * Get metrics data
     * @param {Object} options - Additional options
     */
    static async getMetrics(options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;

        try {
            if (onStart) onStart();

            const response = await axios.get('/api/dashboard/metrics');

            if (onSuccess) onSuccess(response.data.data);
            return response.data.data;
        } catch (error) {
            const errorMessage = error.response?.data?.message || 'Không thể tải dữ liệu metrics!';
            ToastService.error(errorMessage);
            if (onError) onError(error);
            throw error;
        } finally {
            if (onFinish) onFinish();
        }
    }

    /**
     * Get pending actions
     * @param {Object} options - Additional options
     */
    static async getPendingActions(options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;

        try {
            if (onStart) onStart();

            const response = await axios.get('/api/dashboard/pending-actions');

            if (onSuccess) onSuccess(response.data.data);
            return response.data.data;
        } catch (error) {
            const errorMessage = error.response?.data?.message || 'Không thể tải danh sách phiếu cần xử lý!';
            ToastService.error(errorMessage);
            if (onError) onError(error);
            throw error;
        } finally {
            if (onFinish) onFinish();
        }
    }

    /**
     * Get chart data
     * @param {string} type - Chart type (status, workflow, trend, department)
     * @param {string} period - Time period (week, month, quarter, year)
     * @param {Object} options - Additional options
     */
    static async getChartData(type, period = 'month', options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;

        try {
            if (onStart) onStart();

            const response = await axios.get('/api/dashboard/chart-data', {
                params: { type, period }
            });

            if (onSuccess) onSuccess(response.data.data);
            return response.data.data;
        } catch (error) {
            const errorMessage = error.response?.data?.message || 'Không thể tải dữ liệu biểu đồ!';
            ToastService.error(errorMessage);
            if (onError) onError(error);
            throw error;
        } finally {
            if (onFinish) onFinish();
        }
    }

    /**
     * Get activities
     * @param {number} limit - Number of activities to fetch
     * @param {string} eventFilter - Filter by event type
     * @param {Object} options - Additional options
     */
    static async getActivities(limit = 15, eventFilter = null, options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;

        try {
            if (onStart) onStart();

            const response = await axios.get('/api/dashboard/activities', {
                params: { limit, event: eventFilter }
            });

            if (onSuccess) onSuccess(response.data.data);
            return response.data.data;
        } catch (error) {
            const errorMessage = error.response?.data?.message || 'Không thể tải hoạt động!';
            ToastService.error(errorMessage);
            if (onError) onError(error);
            throw error;
        } finally {
            if (onFinish) onFinish();
        }
    }

    /**
     * Get workflow severity for tags
     * @param {string} type - Workflow type
     * @returns {string} - PrimeVue severity
     */
    static getWorkflowSeverity(type) {
        const severityMap = {
            'URGENT': 'danger',
            'SPECIAL_4': 'danger',
            'SPECIAL_1': 'warn',
            'SPECIAL_2': 'warn',
            'SPECIAL_3': 'warn',
            'NORMAL': 'info',
        };
        return severityMap[type] || 'secondary';
    }

    /**
     * Get activity color for timeline
     * @param {string} event - Activity event type
     * @returns {string} - Color hex
     */
    static getActivityColor(event) {
        const colorMap = {
            'created': '#64748b',
            'submitted': '#3b82f6',
            'approved': '#22c55e',
            'rejected': '#ef4444',
            'cancelled': '#6b7280',
        };
        return colorMap[event] || '#64748b';
    }

    /**
     * Get activity icon for timeline
     * @param {string} event - Activity event type
     * @returns {string} - PrimeIcons class
     */
    static getActivityIcon(event) {
        const iconMap = {
            'created': 'pi pi-plus',
            'submitted': 'pi pi-send',
            'approved': 'pi pi-check',
            'rejected': 'pi pi-times',
            'cancelled': 'pi pi-ban',
        };
        return iconMap[event] || 'pi pi-circle';
    }

    /**
     * Format date for display
     * @param {string} dateString - ISO date string
     * @returns {string} - Formatted date
     */
    static formatDate(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) return 'Vừa xong';
        if (diffMins < 60) return `${diffMins} phút trước`;
        if (diffHours < 24) return `${diffHours} giờ trước`;
        if (diffDays < 7) return `${diffDays} ngày trước`;

        return date.toLocaleDateString('vi-VN', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
}
