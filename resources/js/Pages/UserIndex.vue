<template>
    <Head>
        <title>{{ t('users.title') }}</title>
    </Head>

    <div>
        <div class="card">
            <Toolbar class="mb-6">
                <template #start>
                    <Button v-if="isSuperAdmin()" :label="t('users.add')" icon="pi pi-plus" class="mr-2" @click="openNew" />
                    <Button v-if="isSuperAdmin()" :label="t('users.delete')" icon="pi pi-trash" severity="danger" variant="outlined" @click="confirmDeleteSelected" :disabled="!selectedUsers || !selectedUsers.length" />
                </template>

                <template #end>
                    <FileUpload v-if="isSuperAdmin()" mode="basic" accept="image/*" :maxFileSize="1000000" :label="t('users.import')" customUpload :chooseLabel="t('users.import')" class="mr-2" auto :chooseButtonProps="{ severity: 'secondary' }" />
                    <Button :label="t('users.export')" icon="pi pi-upload" severity="secondary" @click="exportCSV($event)" />
                </template>
            </Toolbar>

            <DataTable
                ref="dt"
                v-model:selection="selectedUsers"
                :value="usersList || []"
                dataKey="id"
                :paginator="true"
                :rows="10"
                :filters="filters"
                paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                :rowsPerPageOptions="[5, 10, 25]"
                currentPageReportTemplate="Hiển thị từ {first} đến {last} trong tổng số {totalRecords} người dùng"
                :loading="loading"
            >
                <template #header>
                    <div class="flex flex-wrap gap-2 items-center justify-between">
                        <h4 class="m-0">{{ t('users.title') }}</h4>
                        <IconField>
                            <InputIcon>
                                <i class="pi pi-search" />
                            </InputIcon>
                            <InputText v-model="filters['global'].value" :placeholder="t('users.search') + '...'" />
                        </IconField>
                    </div>
                </template>

                <Column selectionMode="multiple" style="width: 3rem" :exportable="false" v-if="isSuperAdmin()"></Column>
                <Column field="name" :header="t('users.name')" sortable style="min-width: 16rem"></Column>
                <Column field="email" :header="t('users.email')" sortable style="min-width: 10rem"></Column>
                <Column field="roles" :header="t('users.roles')" style="min-width: 12rem">
                    <template #body="slotProps">
                        <Tag v-for="role in slotProps.data.roles" :key="role.id" :value="role.name" severity="info" class="mr-1" />
                    </template>
                </Column>
                <Column field="status" :header="t('users.status')" style="min-width: 8rem">
                    <template #body="slotProps">
                        <Badge v-if="slotProps.data.is_deleted" value="Đã xóa" severity="danger" />
                        <Badge v-else value="Hoạt động" severity="success" />
                    </template>
                </Column>
                <Column field="created_at" :header="t('users.createdAt')" sortable style="min-width: 10rem">
                    <template #body="slotProps">
                        {{ formatDate(slotProps.data.created_at) }}
                    </template>
                </Column>
                <Column v-if="isSuperAdmin()" :header="t('users.actions')" :exportable="false" style="min-width: 16rem">
                    <template #body="slotProps">
                        <div v-if="!slotProps.data.is_deleted" class="flex gap-2">
                            <Button icon="pi pi-pencil" variant="outlined" rounded @click="editUser(slotProps.data)" />
                            <Button icon="pi pi-trash" variant="outlined" rounded severity="danger" @click="confirmDeleteUser(slotProps.data)" />
                        </div>
                        <div v-else class="flex gap-2">
                            <Button icon="pi pi-refresh" variant="outlined" rounded severity="success" @click="confirmRestoreUser(slotProps.data)" v-tooltip="t('common.restore')" />
                            <Button icon="pi pi-times" variant="outlined" rounded severity="danger" @click="confirmForceDeleteUser(slotProps.data)" v-tooltip="t('common.forceDelete')" />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <!-- Add/Edit User Dialog -->
        <Dialog v-model:visible="userDialog" :style="{ width: '450px' }" :header="isEditing ? t('users.editUser') : t('users.addUser')" :modal="true">
            <div class="flex flex-col gap-6">
                <div>
                    <label for="name" class="block font-bold mb-3">{{ t('users.name') }}</label>
                    <InputText
                        id="name"
                        v-model.trim="user.name"
                        required="true"
                        autofocus
                        :invalid="submitted && !user.name || hasError('name')"
                        fluid
                    />
                    <small v-if="submitted && !user.name" class="text-red-500">{{ t('users.nameRequired') }}</small>
                    <small v-if="hasError('name')" class="p-error block mt-1">{{ t(getError('name')) }}</small>
                </div>
                <div>
                    <label for="email" class="block font-bold mb-3">{{ t('users.email') }}</label>
                    <InputText
                        id="email"
                        v-model.trim="user.email"
                        required="true"
                        :invalid="submitted && !user.email || hasError('email')"
                        fluid
                    />
                    <small v-if="submitted && !user.email" class="text-red-500">{{ t('users.emailRequired') }}</small>
                    <small v-if="hasError('email')" class="p-error block mt-1">{{ t(getError('email')) }}</small>
                </div>
                <div>
                    <label for="roles" class="block font-bold mb-3">{{ t('users.roles') }}</label>
                    <MultiSelect
                        id="roles"
                        v-model="user.roles"
                        :options="props.roles"
                        optionLabel="name"
                        optionValue="id"
                        :placeholder="t('users.selectRoles')"
                        :invalid="submitted && (!user.roles || user.roles.length === 0) || hasError('roles')"
                        fluid
                        display="chip"
                    />
                    <small v-if="submitted && (!user.roles || user.roles.length === 0)" class="text-red-500">Vai trò là bắt buộc</small>
                    <small v-if="hasError('roles')" class="p-error block mt-1">{{ t(getError('roles')) }}</small>
                </div>
                <div v-if="!isEditing">
                    <label for="password" class="block font-bold mb-3">{{ t('users.password') }}</label>
                    <InputText
                        id="password"
                        v-model="user.password"
                        type="password"
                        required="true"
                        :invalid="submitted && !user.password || hasError('password')"
                        fluid
                    />
                    <small v-if="submitted && !user.password" class="text-red-500">{{ t('users.passwordRequired') }}</small>
                    <small v-if="hasError('password')" class="p-error block mt-1">{{ t(getError('password')) }}</small>
                </div>
                <div v-if="!isEditing">
                    <label for="password_confirmation" class="block font-bold mb-3">{{ t('users.confirmPassword') }}</label>
                    <InputText
                        id="password_confirmation"
                        v-model="user.password_confirmation"
                        type="password"
                        required="true"
                        :invalid="submitted && !user.password_confirmation || hasError('password_confirmation')"
                        fluid
                    />
                    <small v-if="submitted && !user.password_confirmation" class="text-red-500">{{ t('users.passwordConfirmRequired') }}</small>
                    <small v-if="hasError('password_confirmation')" class="p-error block mt-1">{{ t(getError('password_confirmation')) }}</small>
                </div>
            </div>

            <template #footer>
                <Button :label="t('users.cancel')" icon="pi pi-times" text @click="hideDialog" />
                <Button :label="t('users.save')" icon="pi pi-check" @click="saveUser" :loading="saving" />
            </template>
        </Dialog>

        <!-- Delete User Dialog -->
        <Dialog v-model:visible="deleteUserDialog" :style="{ width: '450px' }" :header="t('users.confirmDelete')" :modal="true">
            <div class="flex items-center gap-4">
                <i class="pi pi-exclamation-triangle !text-3xl" />
                <span v-if="user">{{ t('users.confirmDeleteMessage').replace('{name}', user.name) }}</span>
            </div>
            <template #footer>
                <Button :label="t('common.no')" icon="pi pi-times" text @click="deleteUserDialog = false" severity="secondary" variant="text" />
                <Button :label="t('common.yes')" icon="pi pi-check" @click="deleteUser" severity="danger" :loading="deleting" />
            </template>
        </Dialog>

        <!-- Delete Multiple Users Dialog -->
        <Dialog v-model:visible="deleteUsersDialog" :style="{ width: '450px' }" :header="t('users.confirmBulkDelete')" :modal="true">
            <div class="flex items-center gap-4">
                <i class="pi pi-exclamation-triangle !text-3xl" />
                <span>{{ t('users.confirmBulkDeleteMessage') }}</span>
            </div>
            <template #footer>
                <Button :label="t('common.no')" icon="pi pi-times" text @click="deleteUsersDialog = false" severity="secondary" variant="text" />
                <Button :label="t('common.yes')" icon="pi pi-check" text @click="deleteSelectedUsers" severity="danger" :loading="deleting" />
            </template>
        </Dialog>

        <!-- Restore User Dialog -->
        <Dialog v-model:visible="restoreUserDialog" :style="{ width: '450px' }" :header="t('common.confirmRestore')" :modal="true">
            <div class="flex items-center gap-4">
                <i class="pi pi-question-circle !text-3xl text-green-500" />
                <span v-if="user">{{ t('common.confirmRestoreMessage').replace('{name}', user.name) }}</span>
            </div>
            <template #footer>
                <Button :label="t('common.cancel')" icon="pi pi-times" text @click="restoreUserDialog = false" severity="secondary" variant="text" />
                <Button :label="t('common.restore')" icon="pi pi-check" @click="restoreUser" severity="success" :loading="restoring" />
            </template>
        </Dialog>

        <!-- Force Delete User Dialog -->
        <Dialog v-model:visible="forceDeleteUserDialog" :style="{ width: '450px' }" :header="t('common.confirmForceDelete')" :modal="true">
            <div class="flex items-center gap-4">
                <i class="pi pi-exclamation-triangle !text-3xl text-red-500" />
                <div>
                    <p v-if="user" class="mb-3">{{ t('common.confirmForceDeleteMessage').replace('{name}', user.name) }}</p>
                    <p class="text-sm text-gray-600">⚠️ {{ t('common.forceDeleteWarning') }}</p>
                </div>
            </div>
            <template #footer>
                <Button :label="t('common.cancel')" icon="pi pi-times" text @click="forceDeleteUserDialog = false" severity="secondary" variant="text" />
                <Button :label="t('common.forceDelete')" icon="pi pi-trash" @click="forceDeleteUser" severity="danger" :loading="forceDeleting" />
            </template>
        </Dialog>
	</div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import { Head, usePage } from '@inertiajs/vue3';
import { UserService } from '@/services';
import { useI18n } from '@/composables/useI18n';
import { useFormValidation } from '@/composables/useFormValidation';
import { usePermission } from '@/composables/usePermission';

// Define props
const props = defineProps({
    users: {
        type: Array,
        default: () => []
    },
    roles: {
        type: Array,
        default: () => []
    }
});

// Composables
const { t } = useI18n();
const { errors, hasError, getError, processing, setProcessing } = useFormValidation();
const { isSuperAdmin } = usePermission();

// Reactive data
const dt = ref();
const usersList = ref(Array.isArray(props.users) ? [...props.users] : []);
const user = ref({});
const selectedUsers = ref([]);
const userDialog = ref(false);
const deleteUserDialog = ref(false);
const deleteUsersDialog = ref(false);
const restoreUserDialog = ref(false);
const forceDeleteUserDialog = ref(false);
const submitted = ref(false);
const loading = ref(false);
const saving = ref(false);
const deleting = ref(false);
const restoring = ref(false);
const forceDeleting = ref(false);

// Filters for DataTable
const filters = ref({
    'global': { value: null, matchMode: FilterMatchMode.CONTAINS }
});

// Computed properties
const isEditing = computed(() => user.value.id);

// Watch for props changes to handle flash messages after operations
const page = usePage();

// Watch for users props changes and update local usersList
watch(() => props.users, (newUsers) => {
    if (Array.isArray(newUsers)) {
        usersList.value = [...newUsers];
    }
}, { immediate: true, deep: true });

// Helper functions
const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('vi-VN');
};

const resetForm = () => {
    user.value = {};
    submitted.value = false;
    // Errors will be automatically cleared when the page re-renders
};

const resetDialogs = () => {
    userDialog.value = false;
    deleteUserDialog.value = false;
    deleteUsersDialog.value = false;
    restoreUserDialog.value = false;
    forceDeleteUserDialog.value = false;
};

// CRUD Operations
const openNew = () => {
    resetForm();
    userDialog.value = true;
};

const hideDialog = () => {
    resetDialogs();
    resetForm();
};

const editUser = (userData) => {
    resetForm();
    user.value = {
        ...userData,
        roles: userData.roles ? userData.roles.map(role => role.id) : []
    };
    userDialog.value = true;
};

const saveUser = () => {
    submitted.value = true;

    // Basic client-side validation
    if (!user.value.name || !user.value.email || !user.value.roles || user.value.roles.length === 0) {
        return;
    }

    if (!isEditing.value && (!user.value.password || !user.value.password_confirmation)) {
        return;
    }

    saving.value = true;

    const userData = {
        name: user.value.name,
        email: user.value.email,
        roles: user.value.roles,
    };

    if (!isEditing.value) {
        userData.password = user.value.password;
        userData.password_confirmation = user.value.password_confirmation;
    }

    const onSuccess = (page) => {
        saving.value = false;
        hideDialog();
        // Update local users list if page data is available
        if (page && page.props && page.props.users) {
            usersList.value = [...page.props.users];
        }
    };

    const onError = (errors) => {
        saving.value = false;
        // Validation errors will be handled automatically by useFormValidation
        // No need to manually handle them here
    };

    if (isEditing.value) {
        UserService.update(user.value.id, userData, {
            onSuccess,
            onError
        });
    } else {
        UserService.store(userData, {
            onSuccess,
            onError
        });
    }
};

const confirmDeleteUser = (userData) => {
    user.value = userData;
    deleteUserDialog.value = true;
};

const deleteUser = () => {
    UserService.destroy(user.value.id, {
        onStart: () => {
            deleting.value = true;
        },
        onSuccess: () => {
            deleting.value = false;
            deleteUserDialog.value = false;
            user.value = {};
        },
        onError: () => {
            deleting.value = false;
            deleteUserDialog.value = false;
            user.value = {};
        },
        onFinish: () => {
            deleting.value = false;
            deleteUserDialog.value = false;
            user.value = {};
        }
    });
};

const confirmDeleteSelected = () => {
    deleteUsersDialog.value = true;
};

const deleteSelectedUsers = () => {
    const userIds = selectedUsers.value.map(user => user.id);

    UserService.bulkDelete(userIds, {
        onStart: () => {
            deleting.value = true;
        },
        onSuccess: () => {
            deleting.value = false;
            deleteUsersDialog.value = false;
            selectedUsers.value = [];
        },
        onError: () => {
            deleting.value = false;
            deleteUsersDialog.value = false;
            selectedUsers.value = [];
        },
        onFinish: () => {
            deleting.value = false;
            deleteUsersDialog.value = false;
            selectedUsers.value = [];
        }
    });
};

const exportCSV = () => {
    dt.value.exportCSV();
};

// Soft Delete Operations
const confirmRestoreUser = (userData) => {
    user.value = userData;
    restoreUserDialog.value = true;
};

const restoreUser = () => {
    UserService.restore(user.value.id, {
        onStart: () => {
            restoring.value = true;
        },
        onSuccess: () => {
            restoring.value = false;
            restoreUserDialog.value = false;
            user.value = {};
        },
        onError: () => {
            restoring.value = false;
            restoreUserDialog.value = false;
            user.value = {};
        },
        onFinish: () => {
            restoring.value = false;
            restoreUserDialog.value = false;
            user.value = {};
        }
    });
};

const confirmForceDeleteUser = (userData) => {
    user.value = userData;
    forceDeleteUserDialog.value = true;
};

const forceDeleteUser = () => {
    UserService.forceDelete(user.value.id, {
        onStart: () => {
            forceDeleting.value = true;
        },
        onSuccess: () => {
            forceDeleting.value = false;
            forceDeleteUserDialog.value = false;
            user.value = {};
        },
        onError: () => {
            forceDeleting.value = false;
            forceDeleteUserDialog.value = false;
            user.value = {};
        },
        onFinish: () => {
            forceDeleting.value = false;
            forceDeleteUserDialog.value = false;
            user.value = {};
        }
    });
};
</script>

