<template>
    <Head>
        <title>Chi tiết phiếu: {{ reportData.code || reportData.title }}</title>
    </Head>

    <div>
        <!-- Header Card -->
        <div class="card mb-4">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-2xl font-bold mb-2">{{ reportData.title }}</h2>
                    <p class="text-gray-600">Mã phiếu: <strong>{{ reportData.code || 'Chưa có mã' }}</strong></p>
                </div>
                <div class="flex gap-2 items-center">
                    <Tag :value="reportData.status_label" :severity="reportData.status_color" class="text-lg px-4 py-2" />
                </div>
            </div>

            <!-- Action buttons -->
            <div class="flex gap-2 pt-4 border-t">
                <Button label="Quay lại" icon="pi pi-arrow-left" severity="secondary" variant="outlined" @click="goBack" />

                <!-- Edit button (only for DRAFT and creator) -->
                <Button
                    v-if="reportData.status === 'DRAFT' && canEdit"
                    label="Chỉnh sửa"
                    severity="warn"
                    icon="pi pi-pencil"
                    @click="editReport"
                />

                <!-- Submit button (only for DRAFT and creator) -->
                <Button
                    v-if="reportData.status === 'DRAFT' && canSubmit"
                    label="Nộp phiếu"
                    icon="pi pi-send"
                    severity="success"
                    @click="confirmSubmit"
                />

                <!-- Approve button (only for IN_APPROVAL and current approver) -->
                <Button
                    v-if="reportData.status === 'IN_APPROVAL' && canApprove"
                    label="Phê duyệt"
                    icon="pi pi-check"
                    severity="success"
                    @click="showApprovalDialog"
                />

                <!-- Reject button (only for IN_APPROVAL and current approver) -->
                <Button
                    v-if="reportData.status === 'IN_APPROVAL' && canApprove"
                    label="Từ chối"
                    icon="pi pi-times"
                    severity="danger"
                    @click="showRejectionDialog"
                />

                <!-- Clone button (only for REJECTED) -->
                <Button
                    v-if="reportData.status === 'REJECTED' && canClone"
                    label="Sao chép phiếu"
                    icon="pi pi-copy"
                    @click="confirmClone"
                />
            </div>
        </div>

        <!-- Tabs -->
        <div class="card">
            <Tabs value="0">
                <TabList>
                    <Tab value="0">Chi tiết</Tab>
                    <Tab value="1">File báo giá</Tab>
                    <Tab value="2">File BOQ/Đề nghị</Tab>
                    <Tab value="3">Nhật ký</Tab>
                </TabList>

                <TabPanels>
                    <!-- Tab 1: Chi tiết phiếu -->
                    <TabPanel value="0">
                    <div class="mb-6">
                        <h3 class="text-xl font-bold mb-4">Thông tin phiếu</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-gray-600 mb-1">Loại quy trình:</p>
                                <Tag :value="reportData.workflow_type_label" severity="info" />
                            </div>

                            <div>
                                <p class="text-gray-600 mb-1">Người tạo:</p>
                                <p class="font-semibold">{{ reportData.creator?.data?.name || reportData.creator?.name }}</p>
                            </div>

                            <div>
                                <p class="text-gray-600 mb-1">Phòng ban:</p>
                                <p class="font-semibold">{{ reportData.creator?.data?.department?.name || reportData.creator?.department?.name }}</p>
                            </div>

                            <div v-if="reportData.purchasing_admin">
                                <p class="text-gray-600 mb-1">Admin mua hàng:</p>
                                <p class="font-semibold">{{ reportData.purchasing_admin?.data?.name || reportData.purchasing_admin?.name }}</p>
                            </div>

                            <div>
                                <p class="text-gray-600 mb-1">Ngày tạo:</p>
                                <p class="font-semibold">{{ formatDate(reportData.created_at) }}</p>
                            </div>

                            <div v-if="reportData.submitted_at">
                                <p class="text-gray-600 mb-1">Ngày nộp:</p>
                                <p class="font-semibold">{{ formatDate(reportData.submitted_at) }}</p>
                            </div>
                        </div>

                        <div class="mt-4" v-if="reportData.content">
                            <p class="text-gray-600 mb-2">Nội dung yêu cầu:</p>
                            <div class="p-4 bg-gray-50 rounded border">
                                <p class="whitespace-pre-wrap">{{ reportData.content }}</p>
                            </div>
                        </div>

                        <div class="mt-4" v-if="reportData.notes">
                            <p class="text-gray-600 mb-2">Ghi chú:</p>
                            <div class="p-4 bg-gray-50 rounded border">
                                <p class="whitespace-pre-wrap">{{ reportData.notes }}</p>
                            </div>
                        </div>

                        <!-- Report Images -->
                        <div class="mt-6" v-if="reportImages.length > 0">
                            <h4 class="text-lg font-bold mb-3">Báo cáo lựa chọn nhà cung cấp</h4>
                            <div class="border rounded-lg overflow-hidden">
                                <div v-for="(file, index) in reportImages" :key="file.id" class="relative">
                                    <Image
                                        :src="`/vendor-reports/files/${file.id}/view`"
                                        :alt="'Báo cáo ' + (index + 1)"
                                        preview
                                        class="w-full"
                                        imageClass="w-full object-contain max-h-[800px]"
                                    />
                                    <div class="absolute top-2 right-2 flex gap-2">
                                        <Button
                                            icon="pi pi-external-link"
                                            severity="secondary"
                                            rounded
                                            size="small"
                                            v-tooltip.left="'Mở trong tab mới'"
                                            @click="viewFile(file.id)"
                                        />
                                        <Button
                                            icon="pi pi-download"
                                            severity="secondary"
                                            rounded
                                            size="small"
                                            v-tooltip.left="'Tải xuống'"
                                            @click="downloadFile(file.id)"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Approval Steps -->
                    <div v-if="props.approvalSteps && props.approvalSteps.length > 0">
                        <h3 class="text-xl font-bold mb-4">Tiến trình phê duyệt</h3>

                        <Timeline :value="props.approvalSteps" class="customized-timeline">
                            <template #marker="slotProps">
                                <span class="flex items-center justify-center text-white rounded-full z-1 shadow-sm"
                                      :class="{
                                          'bg-green-500': slotProps.item.status === 'APPROVED',
                                          'bg-red-500': slotProps.item.status === 'REJECTED',
                                          'bg-blue-500': slotProps.item.status === 'PENDING',
                                          'bg-gray-400': slotProps.item.status === 'SKIPPED'
                                      }"
                                      style="width: 2.5rem; height: 2.5rem">
                                    <i class="pi" :class="{
                                        'pi-check': slotProps.item.status === 'APPROVED',
                                        'pi-times': slotProps.item.status === 'REJECTED',
                                        'pi-clock': slotProps.item.status === 'PENDING',
                                        'pi-minus': slotProps.item.status === 'SKIPPED'
                                    }"></i>
                                </span>
                            </template>
                            <template #content="slotProps">
                                <div class="p-4 bg-white rounded border" :class="{
                                    'border-green-300': slotProps.item.status === 'APPROVED',
                                    'border-red-300': slotProps.item.status === 'REJECTED',
                                    'border-blue-300': slotProps.item.status === 'PENDING',
                                    'border-gray-300': slotProps.item.status === 'SKIPPED'
                                }">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="font-bold text-lg">{{ slotProps.item.step_key_label }}</h4>
                                        <Tag :value="slotProps.item.status_label" :severity="slotProps.item.status_color" />
                                    </div>

                                    <div v-if="slotProps.item.assignee_user" class="flex items-center gap-2 text-gray-700 mb-2">
                                        <i class="pi pi-user"></i>
                                        <span>{{ slotProps.item.assignee_user.name }}</span>
                                    </div>

                                    <div v-if="slotProps.item.approved_at || slotProps.item.rejected_at" class="text-sm text-gray-600">
                                        <i class="pi pi-calendar mr-1"></i>
                                        {{ formatDate(slotProps.item.approved_at || slotProps.item.rejected_at) }}
                                    </div>

                                    <div v-if="slotProps.item.note" class="mt-2 p-2 bg-gray-50 rounded text-sm">
                                        <strong>Ghi chú:</strong> {{ slotProps.item.note }}
                                    </div>

                                    <div v-if="slotProps.item.rejection_note" class="mt-2 p-2 bg-red-50 rounded text-sm text-red-700">
                                        <strong>Lý do từ chối:</strong> {{ slotProps.item.rejection_note }}
                                    </div>
                                </div>
                            </template>
                        </Timeline>
                    </div>
                    </TabPanel>

                    <!-- Tab 2: File báo giá -->
                    <TabPanel value="1">
                    <h3 class="text-xl font-bold mb-4">Danh sách file báo giá</h3>

                    <DataTable v-if="quotationFiles.length > 0" :value="quotationFiles" stripedRows>
                        <Column field="original_filename" header="Tên file" style="min-width: 300px">
                            <template #body="slotProps">
                                <div class="flex items-center gap-2">
                                    <i class="pi pi-file text-blue-500"></i>
                                    <span>{{ slotProps.data.original_filename }}</span>
                                </div>
                            </template>
                        </Column>
                        <Column field="file_size" header="Kích thước" style="width: 120px">
                            <template #body="slotProps">
                                {{ formatFileSize(slotProps.data.file_size) }}
                            </template>
                        </Column>
                        <Column field="created_at" header="Ngày tạo" style="width: 150px">
                            <template #body="slotProps">
                                {{ formatDate(slotProps.data.created_at) }}
                            </template>
                        </Column>
                        <Column header="Thao tác" style="width: 180px">
                            <template #body="slotProps">
                                <div class="flex gap-2">
                                    <Button
                                        label="Xem"
                                        icon="pi pi-eye"
                                        size="small"
                                        severity="info"
                                        variant="outlined"
                                        @click="viewFile(slotProps.data.id)"
                                    />
                                    <Button
                                        label="Tải"
                                        icon="pi pi-download"
                                        size="small"
                                        severity="secondary"
                                        variant="outlined"
                                        @click="downloadFile(slotProps.data.id)"
                                    />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                    <div v-else class="text-center py-8 text-gray-500">
                        <i class="pi pi-inbox text-4xl mb-3"></i>
                        <p>Chưa có file báo giá</p>
                    </div>
                    </TabPanel>

                    <!-- Tab 3: File BOQ/Đề nghị -->
                    <TabPanel value="2">
                    <h3 class="text-xl font-bold mb-4">File Đề nghị/BOQ</h3>
                    <div>
                        <DataTable v-if="boqFiles.length > 0" :value="boqFiles" stripedRows>
                            <Column field="original_filename" header="Tên file" style="min-width: 300px">
                                <template #body="slotProps">
                                    <div class="flex items-center gap-2">
                                        <i class="pi pi-file text-purple-500"></i>
                                        <span>{{ slotProps.data.original_filename }}</span>
                                    </div>
                                </template>
                            </Column>
                            <Column field="file_size" header="Kích thước" style="width: 120px">
                                <template #body="slotProps">
                                    {{ formatFileSize(slotProps.data.file_size) }}
                                </template>
                            </Column>
                            <Column field="created_at" header="Ngày tạo" style="width: 150px">
                                <template #body="slotProps">
                                    {{ formatDate(slotProps.data.created_at) }}
                                </template>
                            </Column>
                            <Column header="Thao tác" style="width: 180px">
                                <template #body="slotProps">
                                    <div class="flex gap-2">
                                        <Button
                                            label="Xem"
                                            icon="pi pi-eye"
                                            size="small"
                                            severity="info"
                                            variant="outlined"
                                            @click="viewFile(slotProps.data.id)"
                                        />
                                        <Button
                                            label="Tải"
                                            icon="pi pi-download"
                                            size="small"
                                            severity="secondary"
                                            variant="outlined"
                                            @click="downloadFile(slotProps.data.id)"
                                        />
                                    </div>
                                </template>
                            </Column>
                        </DataTable>
                        <div v-else class="text-center py-8 text-gray-500">
                            <i class="pi pi-inbox text-4xl mb-3"></i>
                            <p>Chưa có file BOQ</p>
                        </div>
                    </div>
                    </TabPanel>

                    <!-- Tab 4: Nhật ký -->
                    <TabPanel value="3">
                    <h3 class="text-xl font-bold mb-4">Lịch sử hoạt động</h3>

                    <DataTable :value="props.activities" stripedRows paginator :rows="10" :rowsPerPageOptions="[5, 10, 20, 50]">
                        <Column field="created_at" header="Thời gian" style="min-width: 160px">
                            <template #body="slotProps">
                                {{ formatDateTime(slotProps.data.created_at) }}
                            </template>
                        </Column>
                        <Column field="causer.name" header="Người thực hiện" style="min-width: 150px">
                            <template #body="slotProps">
                                {{ slotProps.data.causer?.name || 'Hệ thống' }}
                            </template>
                        </Column>
                        <Column field="description_formatted" header="Hoạt động" style="min-width: 300px">
                            <template #body="slotProps">
                                <div class="space-y-1">
                                    <div>
                                        <Tag :value="slotProps.data.description_label" severity="info" class="mb-1" />
                                    </div>
                                    <div class="text-sm text-gray-700 whitespace-pre-line">
                                        {{ slotProps.data.description_formatted }}
                                    </div>
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                    </TabPanel>
                </TabPanels>
            </Tabs>
        </div>

        <!-- Submit Confirmation Dialog -->
        <Dialog v-model:visible="submitDialog" :style="{ width: '450px' }" header="Xác nhận nộp phiếu" :modal="true">
            <div class="flex items-center gap-4">
                <i class="pi pi-question-circle !text-3xl text-blue-500" />
                <span>Bạn có chắc chắn muốn nộp phiếu này?</span>
            </div>
            <p class="text-sm text-gray-600 mt-3">Sau khi nộp, phiếu sẽ được chuyển vào quy trình phê duyệt và bạn không thể chỉnh sửa.</p>
            <template #footer>
                <Button label="Hủy" icon="pi pi-times" text @click="submitDialog = false" />
                <Button label="Nộp phiếu" icon="pi pi-send" severity="success" @click="submitReport" :loading="submitting" />
            </template>
        </Dialog>

        <!-- Approval Dialog -->
        <Dialog v-model:visible="approvalDialog" :style="{ width: '500px' }" header="Phê duyệt phiếu" :modal="true">
            <div class="flex flex-col gap-4">
                <div v-if="requiresSelection">
                    <label for="next_approver" class="block font-bold mb-3">Chọn người phê duyệt tiếp theo <span class="text-red-500">*</span></label>
                    <Select
                        id="next_approver"
                        v-model="approvalForm.selected_next_approver_id"
                        :options="props.selectableApprovers"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="Chọn người phê duyệt"
                        fluid
                        filter
                    />
                </div>

                <div>
                    <label for="approval_note" class="block font-bold mb-3">Ghi chú</label>
                    <Textarea
                        id="approval_note"
                        v-model="approvalForm.note"
                        rows="4"
                        fluid
                        placeholder="Nhập ghi chú (không bắt buộc)..."
                    />
                </div>
            </div>
            <template #footer>
                <Button label="Hủy" icon="pi pi-times" text @click="approvalDialog = false" />
                <Button label="Phê duyệt" icon="pi pi-check" severity="success" @click="approveReport" :loading="approving" />
            </template>
        </Dialog>

        <!-- Rejection Dialog -->
        <Dialog v-model:visible="rejectionDialog" :style="{ width: '500px' }" header="Từ chối phiếu" :modal="true">
            <div class="flex flex-col gap-4">
                <div>
                    <label for="rejection_note" class="block font-bold mb-3">Lý do từ chối <span class="text-red-500">*</span></label>
                    <Textarea
                        id="rejection_note"
                        v-model="rejectionForm.rejection_note"
                        rows="4"
                        fluid
                        placeholder="Nhập lý do từ chối (bắt buộc)..."
                    />
                    <small v-if="rejectionSubmitted && !rejectionForm.rejection_note" class="text-red-500 block mt-1">Lý do từ chối là bắt buộc</small>
                </div>
            </div>
            <template #footer>
                <Button label="Hủy" icon="pi pi-times" text @click="rejectionDialog = false" />
                <Button label="Từ chối" icon="pi pi-times-circle" severity="danger" @click="rejectReport" :loading="rejecting" />
            </template>
        </Dialog>

        <!-- Clone Confirmation Dialog -->
        <Dialog v-model:visible="cloneDialog" :style="{ width: '450px' }" header="Xác nhận sao chép phiếu" :modal="true">
            <div class="flex items-center gap-4">
                <i class="pi pi-question-circle !text-3xl text-blue-500" />
                <span>Bạn có muốn tạo phiếu mới từ phiếu này?</span>
            </div>
            <p class="text-sm text-gray-600 mt-3">Phiếu mới sẽ được tạo với nội dung tương tự ở trạng thái Nháp.</p>
            <template #footer>
                <Button label="Hủy" icon="pi pi-times" text @click="cloneDialog = false" />
                <Button label="Sao chép" icon="pi pi-copy" @click="cloneReport" :loading="cloning" />
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { VendorReportService } from '@/services';

import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Badge from 'primevue/badge';
import Timeline from 'primevue/timeline';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Image from 'primevue/image';

// Define props
const props = defineProps({
    report: {
        type: Object,
        required: true
    },
    approvalSteps: {
        type: Array,
        default: () => []
    },
    currentStep: {
        type: Object,
        default: null
    },
    selectableApprovers: {
        type: Array,
        default: () => []
    },
    files: {
        type: Array,
        default: () => []
    },
    activities: {
        type: Array,
        default: () => []
    },
    canEdit: {
        type: Boolean,
        default: false
    },
    canSubmit: {
        type: Boolean,
        default: false
    },
    canApprove: {
        type: Boolean,
        default: false
    },
    canClone: {
        type: Boolean,
        default: false
    }
});

// Reactive data
const submitDialog = ref(false);
const approvalDialog = ref(false);
const rejectionDialog = ref(false);
const cloneDialog = ref(false);
const submitting = ref(false);
const approving = ref(false);
const rejecting = ref(false);
const cloning = ref(false);
const rejectionSubmitted = ref(false);

const approvalForm = ref({
    selected_next_approver_id: null,
    note: ''
});

const rejectionForm = ref({
    rejection_note: ''
});

// Computed
const reportData = computed(() => props.report?.data || props.report);

const quotationFiles = computed(() => {
    return props.files.filter(file => file.type === 'QUOTATION');
});

const boqFiles = computed(() => {
    return props.files.filter(file => file.type === 'BOQ');
});

const reportImages = computed(() => {
    return props.files.filter(file => file.type === 'REPORT_IMAGE');
});

const requiresSelection = computed(() => {
    return props.currentStep && props.currentStep.requires_selection && props.selectableApprovers.length > 0;
});

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

const formatDateTime = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleString('vi-VN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
};

const goBack = () => {
    router.get('/vendor-reports');
};

const editReport = () => {
    router.get(`/vendor-reports/${reportData.value.id}/edit`);
};

// Actions
const confirmSubmit = () => {
    submitDialog.value = true;
};

const submitReport = () => {
    VendorReportService.submit(reportData.value.id, {
        onStart: () => {
            submitting.value = true;
        },
        onSuccess: () => {
            submitting.value = false;
            submitDialog.value = false;
        },
        onError: () => {
            submitting.value = false;
        }
    });
};

const showApprovalDialog = () => {
    approvalForm.value = {
        selected_next_approver_id: null,
        note: ''
    };
    approvalDialog.value = true;
};

const approveReport = () => {
    if (requiresSelection.value && !approvalForm.value.selected_next_approver_id) {
        return;
    }

    VendorReportService.approve(reportData.value.id, approvalForm.value, {
        onStart: () => {
            approving.value = true;
        },
        onSuccess: () => {
            approving.value = false;
            approvalDialog.value = false;
        },
        onError: () => {
            approving.value = false;
        }
    });
};

const showRejectionDialog = () => {
    rejectionForm.value = {
        rejection_note: ''
    };
    rejectionSubmitted.value = false;
    rejectionDialog.value = true;
};

const rejectReport = () => {
    rejectionSubmitted.value = true;

    if (!rejectionForm.value.rejection_note) {
        return;
    }

    VendorReportService.reject(reportData.value.id, rejectionForm.value, {
        onStart: () => {
            rejecting.value = true;
        },
        onSuccess: () => {
            rejecting.value = false;
            rejectionDialog.value = false;
        },
        onError: () => {
            rejecting.value = false;
        }
    });
};

const confirmClone = () => {
    cloneDialog.value = true;
};

const cloneReport = () => {
    VendorReportService.clone(reportData.value.id, {
        onStart: () => {
            cloning.value = true;
        },
        onSuccess: () => {
            cloning.value = false;
            cloneDialog.value = false;
        },
        onError: () => {
            cloning.value = false;
        }
    });
};

// Utility functions
const formatFileSize = (bytes) => {
    if (!bytes) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
};

const viewFile = (fileId) => {
    window.open(`/vendor-reports/files/${fileId}/view`, '_blank');
};

const downloadFile = (fileId) => {
    window.location.href = `/vendor-reports/files/${fileId}/download`;
};
</script>

<style scoped>
:deep(.customized-timeline .p-timeline-event-content) {
    flex: 1;
}
</style>
