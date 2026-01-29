<template>
    <Head>
        <title>Quản lý phiếu đề nghị</title>
    </Head>

    <div>
        <div class="card">
            <Toolbar class="mb-6">
                <template #start>
                    <Button label="Tạo phiếu mới" icon="pi pi-plus" @click="createNew" />
                </template>

                <template #end>
                    <Button label="Xuất Excel" icon="pi pi-upload" severity="secondary" @click="exportCSV($event)" />
                </template>
            </Toolbar>

            <DataTable
                ref="dt"
                :value="reportsList || []"
                dataKey="id"
                :paginator="true"
                :rows="10"
                :filters="filters"
                paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                :rowsPerPageOptions="[5, 10, 25, 50]"
                currentPageReportTemplate="Hiển thị từ {first} đến {last} trong tổng số {totalRecords} phiếu"
                :loading="loading"
            >
                <template #header>
                    <div class="flex flex-col gap-4">
                        <div class="flex flex-wrap gap-2 items-center justify-between">
                            <h4 class="m-0">Danh sách phiếu đề nghị</h4>
                            <IconField>
                                <InputIcon>
                                    <i class="pi pi-search" />
                                </InputIcon>
                                <InputText v-model="searchQuery" placeholder="Tìm kiếm..." @input="onSearch" />
                            </IconField>
                        </div>

                        <!-- Filters Row -->
                        <div class="flex flex-wrap gap-2">
                            <Select
                                v-model="statusFilter"
                                :options="statusOptions"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Trạng thái"
                                class="w-48"
                                @change="applyFilters"
                                showClear
                            />
                            <Select
                                v-model="workflowTypeFilter"
                                :options="workflowTypeOptions"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Loại quy trình"
                                class="w-48"
                                @change="applyFilters"
                                showClear
                            />
                            <Select
                                v-model="departmentFilter"
                                :options="props.departments"
                                optionLabel="name"
                                optionValue="id"
                                placeholder="Phòng ban"
                                class="w-52"
                                @change="applyFilters"
                                showClear
                                filter
                            />
                        </div>
                    </div>
                </template>

                <Column field="code" header="Mã phiếu" sortable style="min-width: 12rem">
                    <template #body="slotProps">
                        <a @click="viewReport(slotProps.data)" class="text-blue-600 hover:underline cursor-pointer font-semibold">
                            {{ slotProps.data.code || 'Chưa có mã' }}
                        </a>
                    </template>
                </Column>
                <Column field="title" header="Tiêu đề" sortable style="min-width: 20rem"></Column>
                <Column field="workflow_type_label" header="Quy trình" style="min-width: 12rem">
                    <template #body="slotProps">
                        <Tag :value="slotProps.data.workflow_type_label" severity="info" />
                    </template>
                </Column>
                <Column field="status" header="Trạng thái" style="min-width: 12rem">
                    <template #body="slotProps">
                        <Tag :value="slotProps.data.status_label" :severity="slotProps.data.status_color" />
                    </template>
                </Column>
                <Column field="creator" header="Người tạo" style="min-width: 14rem">
                    <template #body="slotProps">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-user text-blue-500"></i>
                            <span>{{ slotProps.data.creator?.name }}</span>
                        </div>
                    </template>
                </Column>
                <Column field="department" header="Phòng ban" style="min-width: 14rem">
                    <template #body="slotProps">
                        {{ slotProps.data.department?.name }}
                    </template>
                </Column>
                <Column field="created_at" header="Ngày tạo" sortable style="min-width: 12rem">
                    <template #body="slotProps">
                        {{ formatDate(slotProps.data.created_at) }}
                    </template>
                </Column>
                <Column header="Thao tác" :exportable="false" style="min-width: 14rem">
                    <template #body="slotProps">
                        <div class="flex gap-2">
                            <Button icon="pi pi-eye" variant="outlined" rounded severity="info" @click="viewReport(slotProps.data)" v-tooltip="'Xem chi tiết'" />

                            <!-- Edit button (only for DRAFT) -->
                            <Button
                                v-if="slotProps.data.status === 'DRAFT'"
                                icon="pi pi-pencil"
                                variant="outlined"
                                rounded
                                @click="editReport(slotProps.data)"
                                v-tooltip="'Chỉnh sửa'"
                            />

                            <!-- Submit button (only for DRAFT) -->
                            <Button
                                v-if="slotProps.data.status === 'DRAFT'"
                                icon="pi pi-send"
                                variant="outlined"
                                rounded
                                severity="success"
                                @click="confirmSubmitReport(slotProps.data)"
                                v-tooltip="'Nộp phiếu'"
                            />

                            <!-- Clone button (only for REJECTED) -->
                            <Button
                                v-if="slotProps.data.status === 'REJECTED'"
                                icon="pi pi-copy"
                                variant="outlined"
                                rounded
                                @click="confirmCloneReport(slotProps.data)"
                                v-tooltip="'Sao chép phiếu'"
                            />

                            <!-- Delete button (only for DRAFT) -->
                            <Button
                                v-if="slotProps.data.status === 'DRAFT'"
                                icon="pi pi-trash"
                                variant="outlined"
                                rounded
                                severity="danger"
                                @click="confirmDeleteReport(slotProps.data)"
                                v-tooltip="'Xóa'"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <!-- Submit Report Dialog -->
        <Dialog v-model:visible="submitDialog" :style="{ width: '450px' }" header="Xác nhận nộp phiếu" :modal="true">
            <div class="flex items-center gap-4">
                <i class="pi pi-question-circle !text-3xl text-blue-500" />
                <span v-if="selectedReport">Bạn có chắc chắn muốn nộp phiếu <strong>{{ selectedReport.title }}</strong>?</span>
            </div>
            <p class="text-sm text-gray-600 mt-3">Sau khi nộp, phiếu sẽ được chuyển vào quy trình phê duyệt và bạn không thể chỉnh sửa.</p>
            <template #footer>
                <Button label="Hủy" icon="pi pi-times" text @click="submitDialog = false" />
                <Button label="Nộp phiếu" icon="pi pi-send" severity="success" @click="submitReport" :loading="submitting" />
            </template>
        </Dialog>

        <!-- Clone Report Dialog -->
        <Dialog v-model:visible="cloneDialog" :style="{ width: '450px' }" header="Xác nhận sao chép phiếu" :modal="true">
            <div class="flex items-center gap-4">
                <i class="pi pi-question-circle !text-3xl text-blue-500" />
                <span v-if="selectedReport">Bạn có muốn tạo phiếu mới từ phiếu <strong>{{ selectedReport.title }}</strong>?</span>
            </div>
            <p class="text-sm text-gray-600 mt-3">Phiếu mới sẽ được tạo với nội dung tương tự ở trạng thái Nháp.</p>
            <template #footer>
                <Button label="Hủy" icon="pi pi-times" text @click="cloneDialog = false" />
                <Button label="Sao chép" icon="pi pi-copy" @click="cloneReport" :loading="cloning" />
            </template>
        </Dialog>

        <!-- Delete Report Dialog -->
        <Dialog v-model:visible="deleteDialog" :style="{ width: '450px' }" header="Xác nhận xóa phiếu" :modal="true">
            <div class="flex items-center gap-4">
                <i class="pi pi-exclamation-triangle !text-3xl text-red-500" />
                <span v-if="selectedReport">Bạn có chắc chắn muốn xóa phiếu <strong>{{ selectedReport.title }}</strong>?</span>
            </div>
            <p class="text-sm text-red-600 mt-3">⚠️ Hành động này không thể hoàn tác!</p>
            <template #footer>
                <Button label="Hủy" icon="pi pi-times" text @click="deleteDialog = false" />
                <Button label="Xóa" icon="pi pi-trash" severity="danger" @click="deleteReport" :loading="deleting" />
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import { Head, router } from '@inertiajs/vue3';
import { VendorReportService } from '@/services';

import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Toolbar from 'primevue/toolbar';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';

// Define props
const props = defineProps({
    reports: {
        type: Array,
        default: () => []
    },
    departments: {
        type: Array,
        default: () => []
    }
});

// Reactive data
const dt = ref();
const reportsList = ref([...props.reports]);
const selectedReport = ref(null);
const submitDialog = ref(false);
const cloneDialog = ref(false);
const deleteDialog = ref(false);
const loading = ref(false);
const submitting = ref(false);
const cloning = ref(false);
const deleting = ref(false);
const searchQuery = ref('');
const statusFilter = ref(null);
const workflowTypeFilter = ref(null);
const departmentFilter = ref(null);

// Filter options
const statusOptions = [
    { label: 'Nháp', value: 'DRAFT' },
    { label: 'Chờ phê duyệt', value: 'IN_APPROVAL' },
    { label: 'Đã phê duyệt', value: 'APPROVED' },
    { label: 'Từ chối', value: 'REJECTED' }
];

const workflowTypeOptions = [
    { label: 'Thường', value: 'NORMAL' },
    { label: 'Đặc biệt 1', value: 'SPECIAL_1' },
    { label: 'Đặc biệt 2', value: 'SPECIAL_2' },
    { label: 'Đặc biệt 3', value: 'SPECIAL_3' },
    { label: 'Khẩn cấp', value: 'URGENT' }
];

// Filters for DataTable
const filters = ref({
    'global': { value: null, matchMode: FilterMatchMode.CONTAINS }
});

// Watch for props changes
watch(() => props.reports, (newReports) => {
    reportsList.value = [...newReports];
}, { deep: true });

// Helper functions
const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('vi-VN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const onSearch = () => {
    applyFilters();
};

const applyFilters = () => {
    VendorReportService.index({
        search: searchQuery.value,
        status: statusFilter.value,
        workflow_type: workflowTypeFilter.value,
        department_id: departmentFilter.value
    });
};

// Navigation
const createNew = () => {
    router.get('/vendor-reports/create');
};

const viewReport = (report) => {
    router.get(`/vendor-reports/${report.id}`);
};

const editReport = (report) => {
    router.get(`/vendor-reports/${report.id}/edit`);
};

// Actions
const confirmSubmitReport = (report) => {
    selectedReport.value = report;
    submitDialog.value = true;
};

const submitReport = () => {
    VendorReportService.submit(selectedReport.value.id, {
        onStart: () => {
            submitting.value = true;
        },
        onSuccess: () => {
            submitting.value = false;
            submitDialog.value = false;
            selectedReport.value = null;
        },
        onError: () => {
            submitting.value = false;
        },
        onFinish: () => {
            submitting.value = false;
        }
    });
};

const confirmCloneReport = (report) => {
    selectedReport.value = report;
    cloneDialog.value = true;
};

const cloneReport = () => {
    VendorReportService.clone(selectedReport.value.id, {
        onStart: () => {
            cloning.value = true;
        },
        onSuccess: () => {
            cloning.value = false;
            cloneDialog.value = false;
            selectedReport.value = null;
        },
        onError: () => {
            cloning.value = false;
        },
        onFinish: () => {
            cloning.value = false;
        }
    });
};

const confirmDeleteReport = (report) => {
    selectedReport.value = report;
    deleteDialog.value = true;
};

const deleteReport = () => {
    VendorReportService.destroy(selectedReport.value.id, {
        onStart: () => {
            deleting.value = true;
        },
        onSuccess: () => {
            deleting.value = false;
            deleteDialog.value = false;
            selectedReport.value = null;
        },
        onError: () => {
            deleting.value = false;
        },
        onFinish: () => {
            deleting.value = false;
        }
    });
};

const exportCSV = () => {
    dt.value.exportCSV();
};
</script>

<style scoped>
</style>
