<template>
    <Head>
        <title>Quản lý phòng ban</title>
    </Head>

    <div>
        <div class="card">
            <Toolbar class="mb-6">
                <template #start>
                    <Button label="Thêm phòng ban" icon="pi pi-plus" class="mr-2" @click="openNew" />
                </template>

                <template #end>
                    <Button label="Xuất Excel" icon="pi pi-upload" severity="secondary" @click="exportCSV($event)" />
                </template>
            </Toolbar>

            <DataTable
                ref="dt"
                v-model:selection="selectedDepartments"
                :value="departmentsList || []"
                dataKey="id"
                :paginator="true"
                :rows="10"
                :filters="filters"
                paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                :rowsPerPageOptions="[5, 10, 25]"
                currentPageReportTemplate="Hiển thị từ {first} đến {last} trong tổng số {totalRecords} phòng ban"
                :loading="loading"
            >
                <template #header>
                    <div class="flex flex-wrap gap-2 items-center justify-between">
                        <h4 class="m-0">Danh sách phòng ban</h4>
                        <div class="flex gap-2">
                            <Select
                                v-model="activeFilter"
                                :options="[
                                    { label: 'Tất cả', value: null },
                                    { label: 'Đang hoạt động', value: true },
                                    { label: 'Ngưng hoạt động', value: false }
                                ]"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Trạng thái"
                                class="w-48"
                                @change="applyFilters"
                            />
                            <IconField>
                                <InputIcon>
                                    <i class="pi pi-search" />
                                </InputIcon>
                                <InputText v-model="filters['global'].value" placeholder="Tìm kiếm..." />
                            </IconField>
                        </div>
                    </div>
                </template>

                <Column field="code" header="Mã phòng ban" sortable style="min-width: 10rem"></Column>
                <Column field="name" header="Tên phòng ban" sortable style="min-width: 16rem"></Column>
                <Column field="head_user" header="Trưởng phòng" style="min-width: 14rem">
                    <template #body="slotProps">
                        <div v-if="slotProps.data.head_user" class="flex items-center gap-2">
                            <i class="pi pi-user text-blue-500"></i>
                            <span>{{ slotProps.data.head_user.name }}</span>
                        </div>
                        <span v-else class="text-gray-400">Chưa có</span>
                    </template>
                </Column>
                <Column field="parent" header="Phòng ban cha" style="min-width: 14rem">
                    <template #body="slotProps">
                        <span v-if="slotProps.data.parent">{{ slotProps.data.parent.name }}</span>
                        <span v-else class="text-gray-400">-</span>
                    </template>
                </Column>
                <Column field="users_count" header="Số nhân viên" sortable style="min-width: 8rem" class="text-center">
                    <template #body="slotProps">
                        <Badge :value="slotProps.data.users_count || 0" severity="info" />
                    </template>
                </Column>
                <Column field="is_active" header="Trạng thái" style="min-width: 10rem">
                    <template #body="slotProps">
                        <Badge v-if="slotProps.data.is_active" value="Hoạt động" severity="success" />
                        <Badge v-else value="Ngưng hoạt động" severity="danger" />
                    </template>
                </Column>
                <Column header="Thao tác" :exportable="false" style="min-width: 12rem">
                    <template #body="slotProps">
                        <div class="flex gap-2">
                            <Button icon="pi pi-pencil" variant="outlined" rounded @click="editDepartment(slotProps.data)" />
                            <Button
                                v-if="slotProps.data.is_active"
                                icon="pi pi-times"
                                variant="outlined"
                                rounded
                                severity="danger"
                                @click="confirmDeactivateDepartment(slotProps.data)"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <!-- Add/Edit Department Dialog -->
        <Dialog v-model:visible="departmentDialog" :style="{ width: '500px' }" :header="isEditing ? 'Cập nhật phòng ban' : 'Thêm phòng ban mới'" :modal="true">
            <div class="flex flex-col gap-6">
                <div>
                    <label for="code" class="block font-bold mb-3">Mã phòng ban <span class="text-red-500">*</span></label>
                    <InputText
                        id="code"
                        v-model="department.code"
                        required="true"
                        autofocus
                        :invalid="submitted && !department.code || hasError('code')"
                        fluid
                    />
                    <small v-if="submitted && !department.code" class="text-red-500">Mã phòng ban là bắt buộc</small>
                    <small v-if="hasError('code')" class="p-error block mt-1">{{ getError('code') }}</small>
                </div>
                <div>
                    <label for="name" class="block font-bold mb-3">Tên phòng ban <span class="text-red-500">*</span></label>
                    <InputText
                        id="name"
                        v-model="department.name"
                        required="true"
                        :invalid="submitted && !department.name || hasError('name')"
                        fluid
                    />
                    <small v-if="submitted && !department.name" class="text-red-500">Tên phòng ban là bắt buộc</small>
                    <small v-if="hasError('name')" class="p-error block mt-1">{{ getError('name') }}</small>
                </div>
                <div>
                    <label for="head_user_id" class="block font-bold mb-3">Trưởng phòng</label>
                    <Select
                        id="head_user_id"
                        v-model="department.head_user_id"
                        :options="props.users"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="Chọn trưởng phòng"
                        :invalid="hasError('head_user_id')"
                        fluid
                        filter
                    />
                    <small v-if="hasError('head_user_id')" class="p-error block mt-1">{{ getError('head_user_id') }}</small>
                </div>
                <div>
                    <label for="parent_id" class="block font-bold mb-3">Phòng ban cha</label>
                    <Select
                        id="parent_id"
                        v-model="department.parent_id"
                        :options="props.allDepartments.filter(d => d.id !== department.id)"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="Chọn phòng ban cha"
                        :invalid="hasError('parent_id')"
                        fluid
                        showClear
                    />
                    <small v-if="hasError('parent_id')" class="p-error block mt-1">{{ getError('parent_id') }}</small>
                </div>
                <div class="flex items-center gap-2">
                    <Checkbox id="is_active" v-model="department.is_active" binary />
                    <label for="is_active">Đang hoạt động</label>
                </div>
            </div>

            <template #footer>
                <Button label="Hủy" icon="pi pi-times" text @click="hideDialog" />
                <Button label="Lưu" icon="pi pi-check" @click="saveDepartment" :loading="saving" />
            </template>
        </Dialog>

        <!-- Deactivate Department Dialog -->
        <Dialog v-model:visible="deactivateDialog" :style="{ width: '450px' }" header="Xác nhận ngưng hoạt động" :modal="true">
            <div class="flex items-center gap-4">
                <i class="pi pi-exclamation-triangle !text-3xl text-orange-500" />
                <span v-if="department">Bạn có chắc chắn muốn ngưng hoạt động phòng ban <strong>{{ department.name }}</strong>?</span>
            </div>
            <template #footer>
                <Button label="Không" icon="pi pi-times" text @click="deactivateDialog = false" />
                <Button label="Có" icon="pi pi-check" severity="danger" @click="deactivateDepartment" :loading="deactivating" />
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import { Head } from '@inertiajs/vue3';
import { DepartmentService } from '@/services';
import { useFormValidation } from '@/composables/useFormValidation';

import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Toolbar from 'primevue/toolbar';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Checkbox from 'primevue/checkbox';
import Badge from 'primevue/badge';
import Tag from 'primevue/tag';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';

// Define props
const props = defineProps({
    departments: {
        type: Array,
        default: () => []
    },
    users: {
        type: Array,
        default: () => []
    },
    allDepartments: {
        type: Array,
        default: () => []
    }
});

// Composables
const { errors, hasError, getError } = useFormValidation();

// Reactive data
const dt = ref();
const departmentsList = ref([...props.departments]);
const department = ref({});
const selectedDepartments = ref([]);
const departmentDialog = ref(false);
const deactivateDialog = ref(false);
const submitted = ref(false);
const loading = ref(false);
const saving = ref(false);
const deactivating = ref(false);
const activeFilter = ref(null);

// Filters for DataTable
const filters = ref({
    'global': { value: null, matchMode: FilterMatchMode.CONTAINS }
});

// Computed properties
const isEditing = computed(() => department.value.id);

const filteredDepartments = computed(() => {
    let result = [...props.departments];

    // Filter by active status
    if (activeFilter.value !== null) {
        result = result.filter(dept => dept.is_active === activeFilter.value);
    }

    return result;
});

// Watch for props changes
watch(() => props.departments, (newDepartments) => {
    departmentsList.value = [...newDepartments];
}, { deep: true });

// Watch filteredDepartments to update the list
watch(filteredDepartments, (filtered) => {
    departmentsList.value = filtered;
}, { immediate: true });

// Helper functions
const resetForm = () => {
    department.value = { is_active: true };
    submitted.value = false;
};

const resetDialogs = () => {
    departmentDialog.value = false;
    deactivateDialog.value = false;
};

const applyFilters = () => {
    // Filters are applied automatically via computed property
};

// CRUD Operations
const openNew = () => {
    resetForm();
    departmentDialog.value = true;
};

const hideDialog = () => {
    resetDialogs();
    resetForm();
};

const editDepartment = (departmentData) => {
    resetForm();
    department.value = {
        ...departmentData,
        head_user_id: departmentData.head_user?.id || null,
        parent_id: departmentData.parent?.id || null,
    };
    departmentDialog.value = true;
};

const saveDepartment = () => {
    submitted.value = true;

    // Basic client-side validation
    if (!department.value.code || !department.value.name) {
        return;
    }

    saving.value = true;

    const departmentData = {
        code: department.value.code,
        name: department.value.name,
        head_user_id: department.value.head_user_id || null,
        parent_id: department.value.parent_id || null,
        is_active: department.value.is_active ? 1 : 0
    };

    const onSuccess = (page) => {
        saving.value = false;
        hideDialog();
        if (page && page.props && page.props.departments) {
            departmentsList.value = [...page.props.departments];
        }
    };

    const onError = () => {
        saving.value = false;
    };

    if (isEditing.value) {
        DepartmentService.update(department.value.id, departmentData, {
            onSuccess,
            onError
        });
    } else {
        DepartmentService.store(departmentData, {
            onSuccess,
            onError
        });
    }
};

const confirmDeactivateDepartment = (departmentData) => {
    department.value = departmentData;
    deactivateDialog.value = true;
};

const deactivateDepartment = () => {
    DepartmentService.destroy(department.value.id, {
        onStart: () => {
            deactivating.value = true;
        },
        onSuccess: () => {
            deactivating.value = false;
            deactivateDialog.value = false;
            department.value = {};
        },
        onError: () => {
            deactivating.value = false;
        },
        onFinish: () => {
            deactivating.value = false;
        }
    });
};

const exportCSV = () => {
    dt.value.exportCSV();
};
</script>

<style scoped>
</style>
