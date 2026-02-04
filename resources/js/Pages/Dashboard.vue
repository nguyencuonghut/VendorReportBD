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
                        <MetricCard v-bind="metric" :loading="refreshing" />
                    </div>
                </div>
            </div>

            <!-- Spacing -->
            <div class="col-12 mb-5"></div>

            <!-- Pending Actions Table -->
            <div class="col-12 lg:col-8">
                <Card>
                    <template #title>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <i class="pi pi-bell text-orange-500"></i>
                                <span>Phiếu cần xử lý</span>
                                <Badge v-if="localPendingActions.length > 0" :value="localPendingActions.length" severity="danger" />
                            </div>
                            <IconField v-if="localPendingActions.length > 0">
                                <InputIcon>
                                    <i class="pi pi-search" />
                                </InputIcon>
                                <InputText
                                    v-model="filters['global'].value"
                                    placeholder="Tìm kiếm..."
                                    size="small"
                                />
                            </IconField>
                        </div>
                    </template>
                    <template #content>
                        <!-- Empty State -->
                        <div v-if="localPendingActions.length === 0" class="flex flex-column align-items-center justify-content-center py-8">
                            <i class="pi pi-check-circle text-6xl text-green-500 mb-4"></i>
                            <h3 class="text-xl font-semibold mb-2">Không có phiếu cần xử lý</h3>
                            <p class="text-500 text-center">Tất cả phiếu đã được xử lý xong.</p>
                        </div>

                        <!-- Data Table -->
                        <DataTable
                            v-else
                            v-model:filters="filters"
                            :value="localPendingActions"
                            :rows="5"
                            :paginator="localPendingActions.length > 5"
                            :globalFilterFields="['code', 'title', 'department_name', 'creator_name']"
                            responsiveLayout="scroll"
                            stripedRows
                            class="p-datatable-sm"
                        >
                            <template #empty>
                                <div class="text-center py-4">
                                    <i class="pi pi-search text-4xl text-400 mb-3"></i>
                                    <p class="text-500">Không tìm thấy phiếu nào.</p>
                                </div>
                            </template>
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
                            <Column field="title" header="Tiêu đề" style="min-width: 200px; max-width: 350px">
                                <template #body="slotProps">
                                    <div
                                        class="text-ellipsis overflow-hidden"
                                        style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; line-height: 1.4; max-height: 2.8em;"
                                        :title="slotProps.data.title"
                                    >
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
                            <Column field="days_pending" header="Thời gian chờ" style="min-width: 150px">
                                <template #body="slotProps">
                                    <Tag
                                        :severity="slotProps.data.days_pending > 5 ? 'danger' : 'secondary'"
                                        :value="slotProps.data.pending_time_formatted"
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
                        <!-- Loading State -->
                        <div v-if="loadingActivities" class="flex flex-column gap-3">
                            <div v-for="i in 5" :key="i" class="flex gap-3">
                                <Skeleton shape="circle" size="2rem" />
                                <div class="flex-1">
                                    <Skeleton width="60%" class="mb-2" />
                                    <Skeleton width="40%" height=".8rem" />
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div v-else-if="localActivities.length === 0" class="flex flex-column align-items-center justify-content-center py-8">
                            <i class="pi pi-inbox text-6xl text-400 mb-4"></i>
                            <h3 class="text-xl font-semibold mb-2">Chưa có hoạt động nào</h3>
                            <p class="text-500 text-center">Các hoạt động của hệ thống sẽ được hiển thị tại đây.</p>
                        </div>

                        <!-- Timeline -->
                        <Timeline v-else :value="localActivities" class="customized-timeline">
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
import { ref, computed, onMounted } from 'vue';
import { router, Link, Head } from '@inertiajs/vue3';
import MetricCard from '@/Components/Dashboard/MetricCard.vue';
import { FilterMatchMode } from '@primevue/core/api';
import DashboardChart from '@/Components/Dashboard/DashboardChart.vue';
import { DashboardService } from '@/services';
import Card from 'primevue/card';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Badge from 'primevue/badge';
import Timeline from 'primevue/timeline';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Skeleton from 'primevue/skeleton';

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

// Filters for DataTable
const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
});

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
