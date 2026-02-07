<template>
    <Head>
        <title>Dashboard</title>
    </Head>

    <div class="grid gap-3">
            <!-- Page Header -->
            <div class="col-12">
                <div class="flex align-items-center justify-content-between mb-2 sm:mb-3">
                    <div>
                        <h3 class="text-xl sm:text-2xl lg:text-3xl font-bold mb-1">Dashboard</h3>
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
                <div class="flex flex-wrap gap-2 sm:gap-3">
                    <div
                        v-for="metric in metrics"
                        :key="metric.id"
                        class="flex-1"
                        style="min-width: min(200px, 100%)"
                    >
                        <MetricCard v-bind="metric" :loading="refreshing" />
                    </div>
                </div>
            </div>

            <!-- Spacing -->
            <div class="col-12 mb-3 sm:mb-5"></div>

            <!-- Pending Actions Table -->
            <div class="col-12 lg:col-8">
                <Card>
                    <template #title>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <i class="pi pi-bell text-orange-500 text-sm sm:text-base"></i>
                                <span class="text-sm sm:text-base">Phiếu cần xử lý</span>
                                <Badge v-if="localPendingActions.length > 0" :value="localPendingActions.length" severity="danger" />
                            </div>
                            <IconField v-if="localPendingActions.length > 0" class="w-full sm:w-auto">
                                <InputIcon>
                                    <i class="pi pi-search" />
                                </InputIcon>
                                <InputText
                                    v-model="filters['global'].value"
                                    placeholder="Tìm kiếm..."
                                    size="small"
                                    class="w-full"
                                />
                            </IconField>
                        </div>
                    </template>
                    <template #content>
                        <!-- Empty State -->
                        <div v-if="localPendingActions.length === 0" class="flex flex-column align-items-center justify-content-center py-6 sm:py-8">
                            <i class="pi pi-check-circle text-4xl sm:text-6xl text-green-500 mb-3 sm:mb-4"></i>
                            <h3 class="text-lg sm:text-xl font-semibold mb-2">Không có phiếu cần xử lý</h3>
                            <p class="text-500 text-center text-sm sm:text-base">Tất cả phiếu đã được xử lý xong.</p>
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
                            :breakpoint="'960px'"
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
                            <Column field="current_step_label" header="Bước hiện tại" style="min-width: 150px">
                                <template #body="slotProps">
                                    <Tag
                                        :severity="getStepSeverity(slotProps.data.current_step_label)"
                                        :value="slotProps.data.current_step_label"
                                    />
                                </template>
                            </Column>
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
                            <i class="pi pi-bolt text-blue-500 text-sm sm:text-base"></i>
                            <span class="text-sm sm:text-base">Thao tác nhanh</span>
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
                                size="small"
                                class="w-full justify-content-start text-sm"
                                @click="router.visit(action.route)"
                            />
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Spacing -->
            <div class="col-12 mb-3 sm:mb-5"></div>

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
            <div class="col-12 mb-3 sm:mb-5"></div>

            <!-- Activities Timeline -->
            <div class="col-12">
                <Card>
                    <template #title>
                        <div class="flex align-items-center justify-content-between">
                            <div class="flex align-items-center gap-2">
                                <i class="pi pi-history text-purple-500 text-sm sm:text-base"></i>
                                <span class="text-sm sm:text-base">Hoạt động gần đây</span>
                            </div>
                            <Button
                                icon="pi pi-refresh"
                                severity="secondary"
                                text
                                rounded
                                size="small"
                                :loading="loadingActivities"
                                @click="refreshActivities"
                            />
                        </div>
                    </template>
                    <template #content>
                        <!-- Loading State -->
                        <div v-if="loadingActivities" class="flex flex-column gap-3">
                            <div v-for="i in 5" :key="i" class="flex gap-2 sm:gap-3">
                                <Skeleton shape="circle" size="1.5rem" class="sm:w-2rem sm:h-2rem" />
                                <div class="flex-1">
                                    <Skeleton width="60%" class="mb-2 h-0.75rem sm:h-1rem" />
                                    <Skeleton width="40%" height=".6rem" class="sm:h-0.8rem" />
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div v-else-if="localActivities.length === 0" class="flex flex-column align-items-center justify-content-center py-6 sm:py-8">
                            <i class="pi pi-inbox text-4xl sm:text-6xl text-400 mb-3 sm:mb-4"></i>
                            <h3 class="text-lg sm:text-xl font-semibold mb-2">Chưa có hoạt động nào</h3>
                            <p class="text-500 text-center text-sm sm:text-base">Các hoạt động của hệ thống sẽ được hiển thị tại đây.</p>
                        </div>

                        <!-- Timeline -->
                        <Timeline v-else :value="localActivities" class="customized-timeline">
                            <template #marker="slotProps">
                                <span
                                    class="flex align-items-center justify-content-center text-white border-circle z-1 shadow-2"
                                    :class="{ 'w-1.5rem h-1.5rem': true, 'sm:w-2rem sm:h-2rem': true }"
                                    :style="{ backgroundColor: DashboardService.getActivityColor(slotProps.item.event) }"
                                >
                                    <i :class="[DashboardService.getActivityIcon(slotProps.item.event), 'text-xs sm:text-sm']"></i>
                                </span>
                            </template>
                            <template #content="slotProps">
                                <div class="flex flex-column gap-1">
                                    <div class="font-semibold text-xs sm:text-sm">{{ slotProps.item.description }}</div>
                                    <div class="text-xs sm:text-sm text-500 flex flex-wrap gap-1">
                                        <span class="font-medium">{{ slotProps.item.causer_name }}</span>
                                        <span>•</span>
                                        <Link
                                            :href="`/vendor-reports/${slotProps.item.report_id}`"
                                            class="text-primary hover:underline"
                                        >
                                            {{ slotProps.item.report_code }}
                                        </Link>
                                        <span>•</span>
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

const getStepSeverity = (stepLabel) => {
    if (!stepLabel) return 'secondary';

    const label = stepLabel.toLowerCase();

    // Đã hoàn tất - Success (xanh lá)
    if (label.includes('hoàn tất') || label.includes('đã duyệt')) {
        return 'success';
    }

    // Ban giám đốc - Danger (đỏ) - Cấp cao nhất, quyết định cuối cùng
    if (label.includes('giám đốc') || label.includes('bod')) {
        return 'danger';
    }

    // Ban kỹ thuật / Mua hàng toàn quốc - Warn (cam) - Các bước đặc biệt ở giữa
    if (label.includes('kỹ thuật') || label.includes('mua hàng') || label.includes('toàn quốc')) {
        return 'warn';
    }

    // Kiểm soát nội bộ - Secondary (xám) - Bước kiểm tra
    if (label.includes('kiểm soát') || label.includes('ksnb')) {
        return 'secondary';
    }

    // Trưởng phòng - Info (xanh dương) - Bước đầu tiên
    if (label.includes('trưởng phòng')) {
        return 'info';
    }

    return 'secondary';
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
