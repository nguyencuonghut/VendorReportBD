<template>
    <Head>
        <title>Nhật ký hoạt động</title>
    </Head>

    <div>
        <div class="card">
            <Toolbar class="mb-6">
                <template #start>
                    <h5 class="m-0">Nhật ký hoạt động</h5>
                </template>

                <template #end>
                    <Button v-if="isSuperAdmin()" label="Xóa tất cả" icon="pi pi-trash" severity="danger" @click="confirmClearLogs" />
                </template>
            </Toolbar>

            <DataTable
                :value="activities.data || []"
                dataKey="id"
                :paginator="false"
                :loading="loading"
                class="p-datatable-sm"
            >
                <template #empty>
                    <div class="text-center p-4">
                        <i class="pi pi-info-circle text-4xl text-gray-400 mb-3"></i>
                        <p class="text-gray-600">Chưa có nhật ký hoạt động nào</p>
                    </div>
                </template>

                <Column field="created_at" header="Thời gian" sortable style="min-width: 12rem">
                    <template #body="slotProps">
                        <div>
                            <div>{{ formatDate(slotProps.data.created_at) }}</div>
                            <small class="text-gray-500">{{ formatTime(slotProps.data.created_at) }}</small>
                        </div>
                    </template>
                </Column>

                <Column field="causer" header="Người thực hiện" style="min-width: 12rem">
                    <template #body="slotProps">
                        <div v-if="slotProps.data.causer">
                            <i class="pi pi-user text-sm mr-2"></i>
                            {{ slotProps.data.causer.name }}
                        </div>
                        <span v-else class="text-gray-400">Hệ thống</span>
                    </template>
                </Column>

                <Column field="description" header="Hoạt động" style="min-width: 20rem">
                    <template #body="slotProps">
                        <div class="flex items-center gap-2">
                            <i :class="getActivityIcon(slotProps.data.description)" class="text-lg"></i>
                            <span>{{ slotProps.data.description }}</span>
                        </div>
                    </template>
                </Column>

                <Column field="subject_type" header="Đối tượng" style="min-width: 12rem">
                    <template #body="slotProps">
                        <Badge v-if="slotProps.data.subject_type" :value="formatSubjectType(slotProps.data.subject_type)" />
                        <span v-else class="text-gray-400">-</span>
                    </template>
                </Column>

                <Column field="properties" header="Chi tiết" style="min-width: 15rem">
                    <template #body="slotProps">
                        <div v-if="slotProps.data.properties && Object.keys(slotProps.data.properties).length > 0">
                            <Button
                                label="Xem"
                                icon="pi pi-eye"
                                text
                                size="small"
                                @click="showProperties(slotProps.data.properties)"
                            />
                        </div>
                        <span v-else class="text-gray-400">-</span>
                    </template>
                </Column>

                <Column v-if="isSuperAdmin()" header="Thao tác" style="min-width: 8rem">
                    <template #body="slotProps">
                        <Button
                            icon="pi pi-trash"
                            variant="outlined"
                            rounded
                            severity="danger"
                            size="small"
                            @click="confirmDelete(slotProps.data)"
                        />
                    </template>
                </Column>
            </DataTable>

            <!-- Pagination -->
            <Paginator
                v-if="activities.data && activities.data.length > 0"
                :rows="activities.per_page"
                :totalRecords="activities.total"
                :rowsPerPageOptions="[10, 20, 50]"
                template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                currentPageReportTemplate="Hiển thị từ {first} đến {last} trong tổng số {totalRecords} bản ghi"
                @page="onPageChange"
            ></Paginator>
        </div>

        <!-- Properties Dialog -->
        <Dialog v-model:visible="propertiesDialog" :style="{ width: '450px' }" header="Chi tiết hoạt động" :modal="true">
            <pre class="bg-gray-100 p-4 rounded text-sm overflow-auto max-h-96">{{ JSON.stringify(selectedProperties, null, 2) }}</pre>
            <template #footer>
                <Button label="Đóng" icon="pi pi-times" @click="propertiesDialog = false" />
            </template>
        </Dialog>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:visible="deleteDialog" :style="{ width: '450px' }" header="Xác nhận xóa" :modal="true">
            <div class="flex items-center gap-4">
                <i class="pi pi-exclamation-triangle !text-3xl text-orange-500" />
                <span>Bạn có chắc chắn muốn xóa bản ghi này không?</span>
            </div>
            <template #footer>
                <Button label="Hủy" icon="pi pi-times" text @click="deleteDialog = false" />
                <Button label="Xóa" icon="pi pi-check" severity="danger" @click="deleteLog" :loading="deleting" />
            </template>
        </Dialog>

        <!-- Clear All Confirmation Dialog -->
        <Dialog v-model:visible="clearLogsDialog" :style="{ width: '450px' }" header="Xác nhận xóa tất cả" :modal="true">
            <div class="flex items-center gap-4">
                <i class="pi pi-exclamation-triangle !text-3xl text-red-500" />
                <div>
                    <p class="mb-3">Bạn có chắc chắn muốn xóa <strong class="text-red-500">TẤT CẢ</strong> nhật ký hoạt động không?</p>
                    <p class="text-sm text-gray-600">⚠️ Hành động này không thể hoàn tác!</p>
                </div>
            </div>
            <template #footer>
                <Button label="Hủy" icon="pi pi-times" text @click="clearLogsDialog = false" />
                <Button label="Xóa tất cả" icon="pi pi-trash" severity="danger" @click="clearAllLogs" :loading="clearing" />
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { usePermission } from '@/composables/usePermission';

// Define props
const props = defineProps({
    activities: {
        type: Object,
        required: true
    },
    filters: {
        type: Object,
        default: () => ({})
    }
});

// Composables
const { isSuperAdmin } = usePermission();

// Reactive data
const loading = ref(false);
const deleting = ref(false);
const clearing = ref(false);
const propertiesDialog = ref(false);
const deleteDialog = ref(false);
const clearLogsDialog = ref(false);
const selectedProperties = ref(null);
const selectedLog = ref(null);

// Helper functions
const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('vi-VN');
};

const formatTime = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleTimeString('vi-VN');
};

const formatSubjectType = (type) => {
    if (!type) return '';
    const parts = type.split('\\');
    return parts[parts.length - 1];
};

const getActivityIcon = (description) => {
    if (!description) return 'pi pi-circle-fill';

    const desc = description.toLowerCase();
    if (desc.includes('tạo') || desc.includes('created')) return 'pi pi-plus-circle text-green-500';
    if (desc.includes('cập nhật') || desc.includes('updated')) return 'pi pi-pencil text-blue-500';
    if (desc.includes('xóa') || desc.includes('deleted')) return 'pi pi-trash text-red-500';
    if (desc.includes('khôi phục') || desc.includes('restored')) return 'pi pi-refresh text-green-500';
    if (desc.includes('sao lưu') || desc.includes('backup')) return 'pi pi-download text-blue-500';

    return 'pi pi-circle-fill text-gray-400';
};

const showProperties = (properties) => {
    selectedProperties.value = properties;
    propertiesDialog.value = true;
};

const confirmDelete = (log) => {
    selectedLog.value = log;
    deleteDialog.value = true;
};

const deleteLog = () => {
    if (!selectedLog.value) return;

    deleting.value = true;

    router.delete(`/activity-logs/${selectedLog.value.id}`, {
        onSuccess: () => {
            deleting.value = false;
            deleteDialog.value = false;
            selectedLog.value = null;
        },
        onError: () => {
            deleting.value = false;
        },
        onFinish: () => {
            deleting.value = false;
        }
    });
};

const confirmClearLogs = () => {
    clearLogsDialog.value = true;
};

const clearAllLogs = () => {
    clearing.value = true;

    router.delete('/activity-logs/clear', {
        onSuccess: () => {
            clearing.value = false;
            clearLogsDialog.value = false;
        },
        onError: () => {
            clearing.value = false;
        },
        onFinish: () => {
            clearing.value = false;
        }
    });
};

const onPageChange = (event) => {
    router.get('/activity-logs', {
        page: event.page + 1,
        per_page: event.rows
    }, {
        preserveState: true,
        preserveScroll: true
    });
};
</script>
