<template>
    <Head>
        <title>Dashboard</title>
    </Head>

    <div class="grid gap-3">
            <!-- Page Header -->
            <div class="col-12">
                <div class="flex align-items-center justify-content-between mb-3">
                    <div>
                        <h3 class="text-3xl font-bold mb-1">Dashboard</h3>
                    </div>
                    <Button
                        icon="pi pi-refresh"
                        severity="success"
                        text
                        rounded
                        v-tooltip.left="'Làm mới'"
                        :loading="refreshing"
                        @click="refreshDashboard"
                    />
                </div>
            </div>

            <!-- Metrics Cards -->
            <div class="col-12">
                <div class="flex flex-wrap gap-3">
                    <div
                        v-for="metric in metrics"
                        :key="metric.id"
                        class="flex-1"
                        style="min-width: 200px"
                    >
                        <MetricCard v-bind="metric" />
                    </div>
                </div>
            </div>

            <!-- Spacing -->
            <div class="col-12 mb-5"></div>

            <!-- Pending Actions Table -->
            <div v-if="localPendingActions.length > 0" class="col-12 lg:col-8">
                <Card>
                    <template #title>
                        <div class="flex align-items-center gap-2">
                            <i class="pi pi-bell text-orange-500"></i>
                            <span>Phiếu cần xử lý</span>
                            <Badge :value="localPendingActions.length" severity="danger" />
                        </div>
                    </template>
                    <template #content>
                        <DataTable
                            :value="localPendingActions"
                            :rows="5"
                            :paginator="pendingActions.length > 5"
                            responsiveLayout="scroll"
                            stripedRows
                            class="p-datatable-sm"
                        >
                            <Column field="code" header="Mã phiếu" style="min-width: 120px">
                                <template #body="slotProps">
                                    <Link
                                        :href="`/vendor-reports/${slotProps.data.id}`"
                                        class="text-primary font-semibold hover:underline"
                                    >
                                        {{ slotProps.data.code }}
                                    </Link>
                                </template>
                            </Column>
                            <Column field="title" header="Tiêu đề" style="min-width: 200px">
                                <template #body="slotProps">
                                    <div class="overflow-hidden text-overflow-ellipsis whitespace-nowrap"
                                         style="max-width: 250px"
                                         :title="slotProps.data.title">
                                        {{ slotProps.data.title }}
                                    </div>
                                </template>
                            </Column>
                            <Column field="department_name" header="Phòng ban" style="min-width: 150px" />
                            <Column field="workflow_type_label" header="Loại" style="min-width: 120px">
                                <template #body="slotProps">
                                    <Tag
                                        :severity="DashboardService.getWorkflowSeverity(slotProps.data.workflow_type)"
                                        :value="slotProps.data.workflow_type_label"
                                    />
                                </template>
                            </Column>
                            <Column field="current_step_label" header="Bước hiện tại" style="min-width: 150px" />
                            <Column field="days_pending" header="Thời gian chờ" style="min-width: 120px">
                                <template #body="slotProps">
                                    <Tag
                                        :severity="slotProps.data.days_pending > 5 ? 'danger' : 'secondary'"
                                        :value="`${Math.round(slotProps.data.days_pending)} ngày`"
                                    />
                                </template>
                            </Column>
                            <Column header="Hành động" style="min-width: 100px">
                                <template #body="slotProps">
                                    <Button
                                        icon="pi pi-eye"
                                        severity="info"
                                        text
                                        rounded
                                        @click="router.visit(`/vendor-reports/${slotProps.data.id}`)"
                                    />
                                </template>
                            </Column>
                        </DataTable>
                    </template>
                </Card>
            </div>

            <!-- Quick Actions -->
            <div class="col-12 lg:col-4">
                <Card>
                    <template #title>
                        <div class="flex align-items-center gap-2">
                            <i class="pi pi-bolt text-blue-500"></i>
                            <span>Thao tác nhanh</span>
                        </div>
                    </template>
                    <template #content>
                        <div class="flex flex-column gap-2">
                            <Button
                                v-for="action in quickActions"
                                :key="action.id"
                                :label="action.label"
                                :icon="action.icon"
                                :severity="action.severity"
                                outlined
                                class="w-full justify-content-start"
                                @click="router.visit(action.route)"
                            />
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Spacing -->
            <div class="col-12 mb-5"></div>

            <!-- Charts Row -->
            <div class="col-12 lg:col-6">
                <DashboardChart
                    title="Thống kê theo trạng thái"
                    type="doughnut"
                    :data="statusChartData"
                    :loading="loadingCharts.status"
                    refreshable
                    @refresh="refreshChart('status')"
                />
            </div>

            <div class="col-12 lg:col-6">
                <DashboardChart
                    title="Xu hướng phê duyệt (6 tháng)"
                    type="line"
                    :data="trendChartData"
                    :options="trendChartOptions"
                    :loading="loadingCharts.trend"
                    refreshable
                    @refresh="refreshChart('trend')"
                />
            </div>

            <!-- Spacing -->
            <div class="col-12 mb-5"></div>

            <!-- Activities Timeline -->
            <div class="col-12">
                <Card>
                    <template #title>
                        <div class="flex align-items-center justify-content-between">
                            <div class="flex align-items-center gap-2">
                                <i class="pi pi-history text-purple-500"></i>
                                <span>Hoạt động gần đây</span>
                            </div>
                            <Button
                                icon="pi pi-refresh"
                                severity="secondary"
                                text
                                rounded
                                :loading="loadingActivities"
                                @click="refreshActivities"
                            />
                        </div>
                    </template>
                    <template #content>
                        <Timeline :value="localActivities" class="customized-timeline">
                            <template #marker="slotProps">
                                <span
                                    class="flex w-2rem h-2rem align-items-center justify-content-center text-white border-circle z-1 shadow-2"
                                    :style="{ backgroundColor: DashboardService.getActivityColor(slotProps.item.event) }"
                                >
                                    <i :class="DashboardService.getActivityIcon(slotProps.item.event)"></i>
                                </span>
                            </template>
                            <template #content="slotProps">
                                <div class="flex flex-column gap-1">
                                    <div class="font-semibold">{{ slotProps.item.description }}</div>
                                    <div class="text-sm text-500">
                                        <span class="font-medium">{{ slotProps.item.causer_name }}</span>
                                        •
                                        <Link
                                            :href="`/vendor-reports/${slotProps.item.report_id}`"
                                            class="text-primary hover:underline"
                                        >
                                            {{ slotProps.item.report_code }}
                                        </Link>
                                        •
                                        <span>{{ DashboardService.formatDate(slotProps.item.created_at) }}</span>
                                    </div>
                                </div>
                            </template>
                        </Timeline>
                    </template>
                </Card>
            </div>
        </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { router, Link, Head } from '@inertiajs/vue3';
import MetricCard from '@/Components/Dashboard/MetricCard.vue';
import DashboardChart from '@/Components/Dashboard/DashboardChart.vue';
import { DashboardService } from '@/services';
import Card from 'primevue/card';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Badge from 'primevue/badge';
import Timeline from 'primevue/timeline';

const props = defineProps({
    metrics: Array,
    pendingActions: Array,
    quickActions: Array,
    activities: Array,
    userRoles: Array,
    isDeptHead: Boolean,
});

// Refs - Local state to avoid mutating props
const refreshing = ref(false);
const loadingActivities = ref(false);
const loadingCharts = ref({
    status: false,
    trend: false,
});

// Local state copies to avoid mutating props
const localActivities = ref([...props.activities]);
const localPendingActions = ref([...props.pendingActions]);

const statusChartData = ref({ labels: [], datasets: [] });
const trendChartData = ref({ labels: [], datasets: [] });

const trendChartOptions = {
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                precision: 0
            }
        }
    }
};

// Methods
const fetchChartData = async (type) => {
    await DashboardService.getChartData(type, 'month', {
        onStart: () => {
            loadingCharts.value[type] = true;
        },
        onSuccess: (data) => {
            if (type === 'status') {
                statusChartData.value = data;
            } else if (type === 'trend') {
                trendChartData.value = data;
            }
        },
        onFinish: () => {
            loadingCharts.value[type] = false;
        }
    });
};

const refreshChart = (type) => {
    fetchChartData(type);
};

const refreshActivities = async () => {
    await DashboardService.getActivities(10, null, {
        onStart: () => {
            loadingActivities.value = true;
        },
        onSuccess: (data) => {
            localActivities.value = data;
        },
        onFinish: () => {
            loadingActivities.value = false;
        }
    });
};

const refreshDashboard = async () => {
    refreshing.value = true;

    try {
        // Reload data from server
        router.reload({
            only: ['metrics', 'pendingActions', 'activities'],
            onSuccess: (page) => {
                // Update local state with new props
                localPendingActions.value = [...page.props.pendingActions];
                localActivities.value = [...page.props.activities];

                // Refresh charts
                fetchChartData('status');
                fetchChartData('trend');
            }
        });
    } finally {
        refreshing.value = false;
    }
};

// Lifecycle
onMounted(() => {
    fetchChartData('status');
    fetchChartData('trend');
});
</script>

<style scoped>
.customized-timeline :deep(.p-timeline-event-content) {
    padding-bottom: 1.5rem;
}

.customized-timeline :deep(.p-timeline-event-opposite) {
    display: none;
}
</style>
