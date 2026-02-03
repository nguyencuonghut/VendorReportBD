<template>
    <Card class="metric-card" :class="{ 'cursor-pointer': onClick }" @click="handleClick">
        <template #content>
            <div class="flex align-items-center justify-content-between mb-3">
                <div class="flex align-items-center gap-2">
                    <i :class="icon" class="text-2xl" :style="{ color: severityColor }"></i>
                    <span class="text-500 font-medium">{{ title }}</span>
                </div>
                <Tag v-if="trend" :severity="getTrendSeverity()" :value="trend" />
            </div>

            <div class="flex align-items-baseline gap-2 mb-2">
                <span class="text-4xl font-bold" :style="{ color: severityColor }">
                    {{ value }}
                </span>
                <span v-if="subtitle" class="text-500 text-sm">{{ subtitle }}</span>
            </div>
        </template>
    </Card>
</template>

<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import Card from 'primevue/card';
import Tag from 'primevue/tag';

const props = defineProps({
    id: String,
    title: String,
    value: [String, Number],
    subtitle: String,
    icon: String,
    severity: {
        type: String,
        default: 'info',
        validator: (value) => ['success', 'info', 'warn', 'danger', 'secondary'].includes(value)
    },
    trend: String,
    onClick: String
});

const severityColorMap = {
    success: '#22c55e',
    info: '#3b82f6',
    warn: '#f59e0b',
    danger: '#ef4444',
    secondary: '#64748b'
};

const severityColor = computed(() => severityColorMap[props.severity] || severityColorMap.info);

const getTrendSeverity = () => {
    if (!props.trend) return 'secondary';
    const value = parseFloat(props.trend);
    if (value > 0) return 'success';
    if (value < 0) return 'danger';
    return 'secondary';
};

const handleClick = () => {
    if (props.onClick) {
        router.visit(props.onClick);
    }
};
</script>

<style scoped>
.metric-card {
    height: 100%;
    transition: all 0.3s ease;
}

.metric-card.cursor-pointer:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.metric-card :deep(.p-card-content) {
    padding: 1.25rem;
}
</style>
