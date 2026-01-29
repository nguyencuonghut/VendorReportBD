<template>
    <Head>
        <title>Quản Lý Vai Trò</title>
    </Head>

    <div>
        <div class="card">
            <Toolbar class="mb-6">
                <template #start>
                    <Button v-if="isSuperAdmin()" label="Thêm vai trò" icon="pi pi-plus" class="mr-2" @click="openNew" />
                    <Button v-if="isSuperAdmin()" label="Xóa" icon="pi pi-trash" severity="danger" variant="outlined" @click="confirmDeleteSelected" :disabled="!selectedRoles || !selectedRoles.length" />
                </template>

                <template #end>
                    <Button label="Xuất dữ liệu" icon="pi pi-upload" severity="secondary" @click="exportCSV($event)" />
                </template>
            </Toolbar>

            <DataTable
                ref="dt"
                v-model:selection="selectedRoles"
                :value="rolesList || []"
                dataKey="id"
                :paginator="true"
                :rows="10"
                :filters="filters"
                paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                :rowsPerPageOptions="[5, 10, 25]"
                currentPageReportTemplate="Hiển thị từ {first} đến {last} trong tổng số {totalRecords} vai trò"
                :loading="loading"
            >
                <template #header>
                    <div class="flex flex-wrap gap-2 items-center justify-between">
                        <h4 class="m-0">Quản Lý Vai Trò</h4>
                        <IconField>
                            <InputIcon>
                                <i class="pi pi-search" />
                            </InputIcon>
                            <InputText v-model="filters['global'].value" placeholder="Tìm kiếm..." />
                        </IconField>
                    </div>
                </template>

                <Column selectionMode="multiple" style="width: 3rem" :exportable="false" v-if="isSuperAdmin()"></Column>
                <Column field="name" header="Tên vai trò" sortable style="min-width: 16rem">
                    <template #body="slotProps">
                        <Tag :value="slotProps.data.name" :severity="getRoleSeverity(slotProps.data.name)" />
                    </template>
                </Column>
                <Column field="permissions_count" header="Số quyền" sortable style="min-width: 10rem">
                    <template #body="slotProps">
                        <Badge :value="slotProps.data.permissions_count || 0" severity="info" />
                    </template>
                </Column>
                <Column field="users_count" header="Số người dùng" sortable style="min-width: 10rem">
                    <template #body="slotProps">
                        <Badge :value="slotProps.data.users_count || 0" severity="success" />
                    </template>
                </Column>
                <Column field="created_at" header="Ngày tạo" sortable style="min-width: 12rem">
                    <template #body="slotProps">
                        {{ formatDate(slotProps.data.created_at) }}
                    </template>
                </Column>
                <Column v-if="isSuperAdmin()" :exportable="false" style="min-width: 12rem">
                    <template #body="slotProps">
                        <Button icon="pi pi-pencil" outlined rounded class="mr-2" @click="editRole(slotProps.data)" />
                        <Button
                            icon="pi pi-trash"
                            outlined
                            rounded
                            severity="danger"
                            @click="confirmDeleteRole(slotProps.data)"
                            :disabled="isSystemRole(slotProps.data.name)"
                        />
                    </template>
                </Column>
            </DataTable>
        </div>

        <!-- Dialog thêm/sửa vai trò -->
        <Dialog v-model:visible="roleDialog" :style="{ width: '650px' }" :header="isEdit ? 'Sửa vai trò' : 'Thêm vai trò'" :modal="true">
            <div class="flex flex-col gap-6">
                <div>
                    <label for="name" class="block font-bold mb-3">Tên vai trò</label>
                    <InputText
                        id="name"
                        v-model="form.name"
                        required="true"
                        autofocus
                        :class="{ 'p-invalid': errors.name }"
                        class="w-full"
                    />
                    <small class="text-red-500" v-if="errors.name">{{ errors.name }}</small>
                </div>

                <div>
                    <label class="block font-bold mb-3">Quyền hạn</label>
                    <div class="grid grid-cols-2 gap-3">
                        <div v-for="permission in allPermissions" :key="permission.id" class="flex items-center">
                            <Checkbox
                                v-model="form.permissions"
                                :inputId="'permission-' + permission.id"
                                :value="permission.id"
                                :disabled="isSystemRole(form.name) && isEdit"
                            />
                            <label :for="'permission-' + permission.id" class="ml-2">{{ permission.name }}</label>
                        </div>
                    </div>
                    <small class="text-red-500" v-if="errors.permissions">{{ errors.permissions }}</small>
                </div>
            </div>

            <template #footer>
                <Button label="Hủy" icon="pi pi-times" text @click="hideDialog" />
                <Button label="Lưu" icon="pi pi-check" @click="saveRole" :loading="form.processing" />
            </template>
        </Dialog>

        <!-- Dialog xác nhận xóa -->
        <Dialog v-model:visible="deleteRoleDialog" :style="{ width: '450px' }" header="Xác nhận" :modal="true">
            <div class="flex items-center gap-4">
                <i class="pi pi-exclamation-triangle !text-3xl" />
                <span v-if="selectedRole">
                    Bạn có chắc chắn muốn xóa vai trò <b>{{ selectedRole.name }}</b>?
                </span>
            </div>
            <template #footer>
                <Button label="Không" icon="pi pi-times" text @click="deleteRoleDialog = false" />
                <Button label="Có" icon="pi pi-check" severity="danger" @click="deleteRole" />
            </template>
        </Dialog>

        <!-- Dialog xác nhận xóa nhiều -->
        <Dialog v-model:visible="deleteRolesDialog" :style="{ width: '450px' }" header="Xác nhận" :modal="true">
            <div class="flex items-center gap-4">
                <i class="pi pi-exclamation-triangle !text-3xl" />
                <span v-if="selectedRoles">
                    Bạn có chắc chắn muốn xóa các vai trò đã chọn?
                </span>
            </div>
            <template #footer>
                <Button label="Không" icon="pi pi-times" text @click="deleteRolesDialog = false" />
                <Button label="Có" icon="pi pi-check" severity="danger" @click="deleteSelectedRoles" />
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import { useFormValidation } from '@/composables/useFormValidation';
import { usePermission } from '@/composables/usePermission';
import { RoleService } from '@/services';

const { errors, hasError, getError } = useFormValidation();
const { isSuperAdmin } = usePermission();

// Props
const props = defineProps({
    roles: {
        type: Array,
        required: true
    },
    permissions: {
        type: Array,
        required: true
    }
});

// Refs
const dt = ref();
const roleDialog = ref(false);
const deleteRoleDialog = ref(false);
const deleteRolesDialog = ref(false);
const selectedRole = ref(null);
const selectedRoles = ref([]);
const loading = ref(false);
const deleting = ref(false);
const isEdit = ref(false);
const filters = ref({
    global: { value: null, matchMode: 'contains' }
});

// Computed
const rolesList = computed(() => props.roles || []);
const allPermissions = computed(() => props.permissions || []);

// Forms
const form = useForm({
    name: '',
    permissions: []
});

const deleteForm = useForm({});

// Methods
const formatDate = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString('vi-VN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const getRoleSeverity = (roleName) => {
    const systemRoles = ['Super Admin', 'Admin', 'Manager', 'User'];
    return systemRoles.includes(roleName) ? 'danger' : 'info';
};

const isSystemRole = (roleName) => {
    const systemRoles = ['Super Admin', 'Admin'];
    return systemRoles.includes(roleName);
};

const openNew = () => {
    form.reset();
    form.clearErrors();
    isEdit.value = false;
    roleDialog.value = true;
};

const hideDialog = () => {
    roleDialog.value = false;
    form.reset();
    form.clearErrors();
};

const editRole = (role) => {
    // Reset form trước khi set giá trị mới
    form.reset();
    form.clearErrors();

    // Set giá trị cho form
    form.name = role.name;
    form.permissions = role.permissions ? role.permissions.map(p => p.id) : [];

    selectedRole.value = role;
    isEdit.value = true;
    roleDialog.value = true;
};

const saveRole = () => {
    const roleData = {
        name: form.name,
        permissions: form.permissions
    };

    const onSuccess = () => {
        hideDialog();
    };

    const onError = (errors) => {
        // Validation errors will be handled automatically by useFormValidation
        // No need to manually handle them here
    };

    if (isEdit.value) {
        RoleService.update(selectedRole.value.id, roleData, {
            onSuccess,
            onError
        });
    } else {
        RoleService.store(roleData, {
            onSuccess,
            onError
        });
    }
};

const confirmDeleteRole = (role) => {
    selectedRole.value = role;
    deleteRoleDialog.value = true;
};

const deleteRole = () => {
    deleteForm.delete(`/roles/${selectedRole.value.id}`, {
        onStart: () => {
            deleting.value = true;
        },
        onSuccess: () => {
            deleting.value = false;
            deleteRoleDialog.value = false;
            selectedRole.value = null;
        },
        onError: () => {
            deleting.value = false;
            deleteRoleDialog.value = false;
            selectedRole.value = null;
        },
        onFinish: () => {
            deleting.value = false;
            deleteRoleDialog.value = false;
            selectedRole.value = null;
        }
    });
};

const confirmDeleteSelected = () => {
    deleteRolesDialog.value = true;
};

const deleteSelectedRoles = () => {
    deleting.value = true;

    const ids = selectedRoles.value.map(role => role.id);

    // Sử dụng useForm cho consistency
    const bulkDeleteForm = useForm({ ids: ids });

    bulkDeleteForm.delete('/roles/bulk-delete', {
        onStart: () => {
            deleting.value = true;
        },
        onSuccess: () => {
            deleting.value = false;
            deleteRolesDialog.value = false;
            selectedRoles.value = [];
        },
        onError: () => {
            deleting.value = false;
            deleteRolesDialog.value = false;
            selectedRoles.value = [];
        },
        onFinish: () => {
            deleting.value = false;
            deleteRolesDialog.value = false;
            selectedRoles.value = [];
        }
    });
};const exportCSV = () => {
    dt.value.exportCSV();
};
</script>

<style scoped>
.p-invalid {
    border-color: #ef4444;
}
</style>
