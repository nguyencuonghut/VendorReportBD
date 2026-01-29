<script setup>
import { ref, computed } from 'vue';
import { useI18n } from '@/composables/useI18n';
import { usePermission } from '@/composables/usePermission';
import AppMenuItem from './AppMenuItem.vue';

const { t } = useI18n();
const { isSuperAdmin } = usePermission();

const model = computed(() => {
    const items = [
        {
            label: 'Trang chủ',
            items: [{ label: 'Trang chủ', icon: 'pi pi-fw pi-home', to: '/' }]
        },
        {
            label: 'Quản lý phiếu',
            items: [
                { label: 'Phiếu đề nghị', icon: 'pi pi-fw pi-file-edit', to: '/vendor-reports' },
                { label: 'Phiếu của tôi', icon: 'pi pi-fw pi-user-edit', to: '/vendor-reports?filter=my-reports' },
                { label: 'Chờ phê duyệt', icon: 'pi pi-fw pi-clock', to: '/vendor-reports?filter=pending-approval' },
            ]
        },
    ];

    // Only show System menu for Super Admin
    if (isSuperAdmin()) {
        items.push({
            label: 'Quản trị',
            items: [
                { label: 'Phòng ban', icon: 'pi pi-fw pi-sitemap', to: '/departments' },
                { label: 'Người dùng', icon: 'pi pi-fw pi-users', to: '/users' },
                { label: 'Vai trò', icon: 'pi pi-fw pi-lock', to: '/roles' },
                {
                    label: 'Backup & Bảo trì',
                    icon: 'pi pi-fw pi-shield',
                    items: [
                        { label: 'Backup thủ công', icon: 'pi pi-fw pi-download', to: '/backup' },
                        { label: 'Auto Backup', icon: 'pi pi-fw pi-clock', to: '/backup/configurations' }
                    ]
                },
                { label: 'Nhật ký hoạt động', icon: 'pi pi-fw pi-list', to: '/activity-logs' }
            ]
        });
    }

    return items;
});
</script>

<template>
    <ul class="layout-menu">
        <template v-for="(item, i) in model" :key="item">
            <app-menu-item v-if="!item.separator" :item="item" :index="i"></app-menu-item>
            <li v-if="item.separator" class="menu-separator"></li>
        </template>
    </ul>
</template>

<style lang="scss" scoped></style>
