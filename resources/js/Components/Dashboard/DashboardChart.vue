<template>
    <Card>
        <template #title>
            <div class="flex align-items-center justify-content-between">
                <span>{{ title }}</span>
                <Button
                    v-if="refreshable"
                    icon="pi pi-refresh"
                    severity="secondary"
                    text
                    rounded
                    :loading="loading"
                    @click="$emit('refresh')"
                />
            </div>
        </template>
        <template #content>
            <div v-if="loading" class="flex align-items-center justify-content-center" style="height: 300px;">
                <ProgressSpinner style="width: 50px; height: 50px" strokeWidth="4" />
            </div>            <div v-else-if="!hasData" class="flex align-items-center justify-content-center" style="height: 300px;">
                <span class="text-500">Không có dữ liệu</span>
            </div>            <Chart
                v-else
                :type="type"
                :data="chartData"
                :options="chartOptions"
                :style="{ height: height }"
            />
        </template>
    </Card>
</template>

<script setup>
import { computed } from 'vue';
import Card from 'primevue/card';
import Chart from 'primevue/chart';
import Button from 'primevue/button';
import ProgressSpinner from 'primevue/progressspinner';

const props = defineProps({
    title: {
        type: String,
        required: true
    },
    type: {
        type: String,
        default: 'bar',
        validator: (value) => ['bar', 'line', 'pie', 'doughnut', 'polarArea', 'radar'].includes(value)
    },
    data: {
        type: Object,
        required: true
    },
    options: {
        type: Object,
        default: () => ({})
    },
    height: {
        type: String,
        default: '300px'
    },
    loading: {
        type: Boolean,
        default: false
    },
    refreshable: {
        type: Boolean,
        default: false
    }
});

defineEmits(['refresh']);

const chartData = computed(() => props.data);

const hasData = computed(() => {
    const data = props.data;
    if (!data || !data.labels || !data.datasets) return false;
    if (data.labels.length === 0) return false;
    if (data.datasets.length === 0) return false;
    return true;
});

const chartOptions = computed(() => {
    const baseOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                labels: {
                    usePointStyle: true,
                    padding: 15
                }
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                padding: 12,
                borderRadius: 6,
                titleFont: {
                    size: 14
                },
                bodyFont: {
                    size: 13
                }
            }
        }
    };

    // Merge with custom options
    return {
        ...baseOptions,
        ...props.options,
        plugins: {
            ...baseOptions.plugins,
            ...(props.options.plugins || {})
        }
    };
});
</script>

<style scoped>
.p-card :deep(.p-card-content) {
    padding-top: 0;
}
</style>
