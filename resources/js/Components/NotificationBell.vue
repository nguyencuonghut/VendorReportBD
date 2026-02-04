<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useNotifications } from '@/composables/useNotifications';
import { useConfirm } from 'primevue/useconfirm';
import Badge from 'primevue/badge';
import Popover from 'primevue/popover';
import Button from 'primevue/button';
import ScrollPanel from 'primevue/scrollpanel';

const {
    notifications,
    unreadCount,
    loading,
    hasMore,
    fetchNotifications,
    fetchUnreadCount,
    markAsRead,
    markAllAsRead,
    deleteNotification,
    deleteAllNotifications,
    loadMore
} = useNotifications();

const confirm = useConfirm();
const popover = ref();
const pollInterval = ref(null);

const toggle = (event) => {
    popover.value.toggle(event);
    if (!notifications.value.length) {
        fetchNotifications();
    }
};

const handleNotificationClick = (notification) => {
    markAsRead(notification.id);
    popover.value.hide();

    if (notification.data?.action_url) {
        router.visit(notification.data.action_url);
    }
};

const handleMarkAllAsRead = () => {
    markAllAsRead();
};

const handleDelete = (notification, event) => {
    event.stopPropagation();
    deleteNotification(notification.id);
};

const handleDeleteAll = () => {
    confirm.require({
        message: 'Bạn có chắc muốn xóa tất cả thông báo?',
        header: 'Xác nhận xóa',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Xóa tất cả',
        rejectLabel: 'Hủy',
        acceptClass: 'p-button-danger',
        accept: async () => {
            await deleteAllNotifications();
            popover.value.hide();
        }
    });
};

const getNotificationIcon = (type) => {
    switch(type) {
        case 'vendor_report_submitted':
        case 'vendor_report_approval_required':
            return 'pi-file-edit';
        case 'vendor_report_approved':
            return 'pi-check-circle';
        case 'vendor_report_rejected':
            return 'pi-times-circle';
        case 'vendor_report_canceled':
            return 'pi-ban';
        default:
            return 'pi-bell';
    }
};

const getNotificationColor = (type) => {
    switch(type) {
        case 'vendor_report_submitted':
        case 'vendor_report_approval_required':
            return 'text-blue-500';
        case 'vendor_report_approved':
            return 'text-green-500';
        case 'vendor_report_rejected':
        case 'vendor_report_canceled':
            return 'text-red-500';
        default:
            return 'text-gray-500';
    }
};

const formatTime = (datetime) => {
    const now = new Date();
    const notifTime = new Date(datetime);
    const diffMs = now - notifTime;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Vừa xong';
    if (diffMins < 60) return `${diffMins} phút trước`;
    if (diffHours < 24) return `${diffHours} giờ trước`;
    if (diffDays < 7) return `${diffDays} ngày trước`;

    return notifTime.toLocaleDateString('vi-VN');
};

onMounted(() => {
    fetchUnreadCount();

    // Poll for new notifications every 30 seconds
    pollInterval.value = setInterval(() => {
        fetchUnreadCount();
    }, 30000);
});

onUnmounted(() => {
    if (pollInterval.value) {
        clearInterval(pollInterval.value);
    }
});
</script>

<template>
    <div class="relative inline-block">
        <Button
            icon="pi pi-bell"
            text
            rounded
            severity="secondary"
            @click="toggle"
        />
        <Badge
            v-if="unreadCount > 0"
            :value="unreadCount > 99 ? '99+' : unreadCount"
            severity="danger"
            class="absolute top-0 right-0 transform translate-x-1/4 -translate-y-1/4"
            style="min-width: 1.25rem; height: 1.25rem;"
        />

        <Popover ref="popover" :style="{ width: '400px' }">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Thông báo</h3>
                <div class="flex gap-2">
                    <Button
                        v-if="unreadCount > 0"
                        label="Đánh dấu tất cả đã đọc"
                        text
                        size="small"
                        @click="handleMarkAllAsRead"
                    />
                    <Button
                        v-if="notifications.length > 0"
                        icon="pi pi-trash"
                        text
                        size="small"
                        severity="danger"
                        v-tooltip.bottom="'Xóa tất cả'"
                        @click="handleDeleteAll"
                    />
                </div>
            </div>

            <ScrollPanel style="height: 400px" class="custom-scrollpanel">
                <div v-if="loading && !notifications.length" class="text-center py-8">
                    <i class="pi pi-spin pi-spinner text-2xl text-gray-400"></i>
                </div>

                <div v-else-if="!notifications.length" class="text-center py-8 text-gray-500">
                    <i class="pi pi-bell-slash text-4xl mb-2"></i>
                    <p>Không có thông báo</p>
                </div>

                <div v-else class="space-y-2">
                    <div
                        v-for="notification in notifications"
                        :key="notification.id"
                        :class="[
                            'p-3 rounded-lg cursor-pointer transition-colors border',
                            notification.read_at ? 'bg-white border-gray-200' : 'bg-blue-50 border-blue-200'
                        ]"
                        @click="handleNotificationClick(notification)"
                    >
                        <div class="flex items-start gap-3">
                            <div :class="['text-xl', getNotificationColor(notification.data?.type)]">
                                <i :class="['pi', getNotificationIcon(notification.data?.type)]"></i>
                            </div>

                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-sm mb-1">
                                    {{ notification.data?.message || 'Thông báo mới' }}
                                </p>
                                <p v-if="notification.data?.report_title" class="text-xs text-gray-600 truncate">
                                    {{ notification.data.report_title }}
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ formatTime(notification.created_at) }}
                                </p>
                            </div>

                            <Button
                                icon="pi pi-times"
                                text
                                rounded
                                size="small"
                                severity="secondary"
                                @click="handleDelete(notification, $event)"
                            />
                        </div>
                    </div>

                    <div v-if="hasMore" class="text-center py-3">
                        <Button
                            label="Tải thêm"
                            text
                            size="small"
                            :loading="loading"
                            @click="loadMore"
                        />
                    </div>
                </div>
            </ScrollPanel>
        </Popover>
    </div>
</template>

<style scoped>
.custom-scrollpanel :deep(.p-scrollpanel-bar) {
    background-color: #cbd5e1;
    opacity: 1;
}
</style>
