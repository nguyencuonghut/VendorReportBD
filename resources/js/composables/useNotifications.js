import { ref } from 'vue';
import axios from 'axios';

export function useNotifications() {
    const notifications = ref([]);
    const unreadCount = ref(0);
    const loading = ref(false);
    const hasMore = ref(true);
    const currentPage = ref(1);

    const fetchNotifications = async (page = 1) => {
        loading.value = true;
        try {
            const response = await axios.get('/api/notifications', {
                params: { page }
            });

            if (page === 1) {
                notifications.value = response.data.data.data;
            } else {
                notifications.value = [...notifications.value, ...response.data.data.data];
            }

            currentPage.value = response.data.data.current_page;
            hasMore.value = response.data.data.current_page < response.data.data.last_page;
        } catch (error) {
            console.error('Failed to fetch notifications:', error);
        } finally {
            loading.value = false;
        }
    };

    const fetchUnreadCount = async () => {
        try {
            const response = await axios.get('/api/notifications/unread-count');
            unreadCount.value = response.data.count;
        } catch (error) {
            console.error('Failed to fetch unread count:', error);
        }
    };

    const markAsRead = async (notificationId) => {
        try {
            await axios.post(`/api/notifications/${notificationId}/mark-as-read`);

            const notification = notifications.value.find(n => n.id === notificationId);
            if (notification && !notification.read_at) {
                notification.read_at = new Date().toISOString();
                unreadCount.value = Math.max(0, unreadCount.value - 1);
            }
        } catch (error) {
            console.error('Failed to mark notification as read:', error);
        }
    };

    const markAllAsRead = async () => {
        try {
            await axios.post('/api/notifications/mark-all-as-read');

            notifications.value.forEach(notification => {
                if (!notification.read_at) {
                    notification.read_at = new Date().toISOString();
                }
            });

            unreadCount.value = 0;
        } catch (error) {
            console.error('Failed to mark all as read:', error);
        }
    };

    const deleteNotification = async (notificationId) => {
        try {
            await axios.delete(`/api/notifications/${notificationId}`);

            const index = notifications.value.findIndex(n => n.id === notificationId);
            if (index !== -1) {
                const notification = notifications.value[index];
                if (!notification.read_at) {
                    unreadCount.value = Math.max(0, unreadCount.value - 1);
                }
                notifications.value.splice(index, 1);
            }
        } catch (error) {
            console.error('Failed to delete notification:', error);
        }
    };

    const clearReadNotifications = async () => {
        try {
            await axios.post('/api/notifications/clear-read');
            notifications.value = notifications.value.filter(n => !n.read_at);
        } catch (error) {
            console.error('Failed to clear read notifications:', error);
        }
    };

    const deleteAllNotifications = async () => {
        try {
            await axios.delete('/api/notifications');
            notifications.value = [];
            unreadCount.value = 0;
        } catch (error) {
            console.error('Failed to delete all notifications:', error);
        }
    };

    const loadMore = () => {
        if (!loading.value && hasMore.value) {
            fetchNotifications(currentPage.value + 1);
        }
    };

    return {
        notifications,
        unreadCount,
        loading,
        hasMore,
        fetchNotifications,
        fetchUnreadCount,
        markAsRead,
        markAllAsRead,
        deleteNotification,
        clearReadNotifications,
        deleteAllNotifications,
        loadMore
    };
}
