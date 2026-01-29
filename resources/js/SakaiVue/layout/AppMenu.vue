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
            label: t('nav.home'),
            items: [{ label: t('nav.home'), icon: 'pi pi-fw pi-home', to: '/' }]
        },
    ];

    // Only show System menu for Super Admin
    if (isSuperAdmin()) {
        items.push({
            label: t('nav.system'),
            items: [
                { label: t('nav.users'), icon: 'pi pi-fw pi-users', to: '/users' },
                { label: t('nav.roles'), icon: 'pi pi-fw pi-lock', to: '/roles' },
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
