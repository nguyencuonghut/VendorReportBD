<template>
    <Head>
        <title>Hồ sơ cá nhân</title>
    </Head>

    <div class="grid">
        <!-- Page Header -->
        <div class="col-12">
            <div class="flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="text-3xl font-bold mb-2">Hồ sơ cá nhân</h3>
                </div>
            </div>
        </div>

        <!-- User Info Card (Read-only) -->
        <div class="col-12">
            <Card>
                <template #title>
                    <div class="flex align-items-center gap-2">
                        <i class="pi pi-user text-blue-500"></i>
                        <span>Thông tin cá nhân</span>
                    </div>
                </template>
                <template #content>
                    <div class="grid">
                        <!-- Name -->
                        <div class="col-12 md:col-6">
                            <div class="flex flex-column gap-2">
                                <label class="font-semibold text-sm text-500">Họ và tên</label>
                                <div class="text-900 font-medium">{{ user.name }}</div>
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="col-12 md:col-6">
                            <div class="flex flex-column gap-2">
                                <label class="font-semibold text-sm text-500">Email</label>
                                <div class="text-900 font-medium">{{ user.email }}</div>
                            </div>
                        </div>

                        <!-- Department -->
                        <div class="col-12 md:col-6">
                            <div class="flex flex-column gap-2">
                                <label class="font-semibold text-sm text-500">Phòng ban</label>
                                <div class="text-900 font-medium">{{ user.department?.name || 'Chưa có' }}</div>
                            </div>
                        </div>

                        <!-- Roles -->
                        <div class="col-12 md:col-6">
                            <div class="flex flex-column gap-2">
                                <label class="font-semibold text-sm text-500">Vai trò</label>
                                <div class="flex flex-wrap gap-2">
                                    <Tag
                                        v-for="role in user.roles"
                                        :key="role.id"
                                        :value="role.display_name"
                                        severity="info"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Account Info -->
                        <div class="col-12">
                            <Divider />
                        </div>

                        <div class="col-12 md:col-4">
                            <div class="flex flex-column gap-2">
                                <label class="font-semibold text-sm text-500">Trạng thái</label>
                                <Tag :value="user.is_active ? 'Đang hoạt động' : 'Ngưng hoạt động'"
                                     :severity="user.is_active ? 'success' : 'danger'"
                                     class="w-fit" />
                            </div>
                        </div>

                        <div class="col-12 md:col-4">
                            <div class="flex flex-column gap-2">
                                <label class="font-semibold text-sm text-500">Đăng nhập lần cuối</label>
                                <div class="text-900 font-medium">{{ user.last_login_at || 'Chưa có' }}</div>
                            </div>
                        </div>

                        <div class="col-12 md:col-4">
                            <div class="flex flex-column gap-2">
                                <label class="font-semibold text-sm text-500">Ngày tạo tài khoản</label>
                                <div class="text-900 font-medium">{{ user.created_at }}</div>
                            </div>
                        </div>

                        <div class="col-12">
                            <Message severity="info" :closable="false">
                                <div class="flex align-items-center gap-2">
                                    <i class="pi pi-info-circle"></i>
                                    <span>Để cập nhật thông tin cá nhân, vui lòng liên hệ Quản trị viên hệ thống</span>
                                </div>
                            </Message>
                        </div>
                    </div>
                </template>
            </Card>
        </div>

        <!-- Change Password Card -->
        <div class="col-12 mt-4">
            <Card>
                <template #title>
                    <div class="flex align-items-center gap-2">
                        <i class="pi pi-lock text-orange-500"></i>
                        <span>Đổi mật khẩu</span>
                    </div>
                </template>
                <template #content>
                    <form @submit.prevent="submitPasswordChange">
                        <div class="flex flex-column gap-4">
                            <!-- Current Password -->
                            <div class="flex flex-column gap-2">
                                <label for="current_password" class="font-semibold">Mật khẩu hiện tại <span class="text-red-500">*</span></label>
                                <Password
                                    id="current_password"
                                    v-model="passwordForm.current_password"
                                    :feedback="false"
                                    toggleMask
                                    :invalid="!!errors.current_password"
                                    :disabled="processingPassword"
                                    inputClass="w-full"
                                    class="w-full"
                                />
                                <small v-if="errors.current_password" class="text-red-500">
                                    {{ errors.current_password }}
                                </small>
                            </div>

                            <!-- New Password -->
                            <div class="flex flex-column gap-2">
                                <label for="password" class="font-semibold">Mật khẩu mới <span class="text-red-500">*</span></label>
                                <Password
                                    id="password"
                                    v-model="passwordForm.password"
                                    toggleMask
                                    :invalid="!!errors.password"
                                    :disabled="processingPassword"
                                    inputClass="w-full"
                                    class="w-full"
                                >
                                    <template #header>
                                        <h6>Chọn mật khẩu</h6>
                                    </template>
                                    <template #footer>
                                        <Divider />
                                        <p class="mt-2 text-sm">Gợi ý</p>
                                        <ul class="pl-3 ml-2 mt-0 text-sm" style="line-height: 1.5">
                                            <li>Ít nhất một chữ thường</li>
                                            <li>Ít nhất một chữ hoa</li>
                                            <li>Ít nhất một chữ số</li>
                                            <li>Tối thiểu 8 ký tự</li>
                                        </ul>
                                    </template>
                                </Password>
                                <small v-if="errors.password" class="text-red-500">{{ errors.password }}</small>
                            </div>

                            <!-- Confirm Password -->
                            <div class="flex flex-column gap-2">
                                <label for="password_confirmation" class="font-semibold">Xác nhận mật khẩu mới <span class="text-red-500">*</span></label>
                                <Password
                                    id="password_confirmation"
                                    v-model="passwordForm.password_confirmation"
                                    :feedback="false"
                                    toggleMask
                                    :invalid="!!errors.password_confirmation"
                                    :disabled="processingPassword"
                                    inputClass="w-full"
                                    class="w-full"
                                />
                                <small v-if="errors.password_confirmation" class="text-red-500">
                                    {{ errors.password_confirmation }}
                                </small>
                            </div>

                            <!-- Submit Button -->
                            <div>
                                <Button
                                    type="submit"
                                    label="Đổi mật khẩu"
                                    icon="pi pi-key"
                                    severity="success"
                                    :loading="processingPassword"
                                />
                            </div>
                        </div>
                    </form>
                </template>
            </Card>
        </div>

        <!-- Statistics Cards -->
        <div class="col-12 mt-4">
            <Card>
                <template #title>
                    <div class="flex align-items-center gap-2">
                        <i class="pi pi-chart-bar text-purple-500"></i>
                        <span>Thống kê</span>
                    </div>
                </template>
                <template #content>
                    <div class="flex flex-wrap gap-3">
                        <!-- Total Reports -->
                        <div class="flex-1" style="min-width: 200px">
                            <Card class="metric-card h-full">
                                <template #content>
                                    <div class="flex align-items-center gap-2 mb-3">
                                        <i class="pi pi-file text-2xl text-blue-500"></i>
                                        <span class="text-500 font-medium">Tổng số phiếu</span>
                                    </div>
                                    <div class="flex align-items-baseline gap-2">
                                        <span class="text-4xl font-bold text-blue-500">{{ statistics.total_reports }}</span>
                                    </div>
                                </template>
                            </Card>
                        </div>

                        <!-- Pending Approvals -->
                        <div class="flex-1" style="min-width: 200px">
                            <Card class="metric-card h-full">
                                <template #content>
                                    <div class="flex align-items-center gap-2 mb-3">
                                        <i class="pi pi-clock text-2xl text-orange-500"></i>
                                        <span class="text-500 font-medium">Cần duyệt</span>
                                    </div>
                                    <div class="flex align-items-baseline gap-2">
                                        <span class="text-4xl font-bold text-orange-500">{{ statistics.pending_approvals }}</span>
                                    </div>
                                </template>
                            </Card>
                        </div>

                        <!-- Approved Reports -->
                        <div class="flex-1" style="min-width: 200px">
                            <Card class="metric-card h-full">
                                <template #content>
                                    <div class="flex align-items-center gap-2 mb-3">
                                        <i class="pi pi-check-circle text-2xl text-green-500"></i>
                                        <span class="text-500 font-medium">Đã duyệt</span>
                                    </div>
                                    <div class="flex align-items-baseline gap-2">
                                        <span class="text-4xl font-bold text-green-500">{{ statistics.approved_reports }}</span>
                                    </div>
                                </template>
                            </Card>
                        </div>

                        <!-- Rejected Reports -->
                        <div class="flex-1" style="min-width: 200px">
                            <Card class="metric-card h-full">
                                <template #content>
                                    <div class="flex align-items-center gap-2 mb-3">
                                        <i class="pi pi-times-circle text-2xl text-red-500"></i>
                                        <span class="text-500 font-medium">Từ chối</span>
                                    </div>
                                    <div class="flex align-items-baseline gap-2">
                                        <span class="text-4xl font-bold text-red-500">{{ statistics.rejected_reports }}</span>
                                    </div>
                                </template>
                            </Card>
                        </div>
                    </div>
                </template>
            </Card>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import Card from 'primevue/card';
import Button from 'primevue/button';
import Password from 'primevue/password';
import Tag from 'primevue/tag';
import Divider from 'primevue/divider';
import Message from 'primevue/message';

const props = defineProps({
    user: Object,
    statistics: Object,
    errors: {
        type: Object,
        default: () => ({})
    }
});

const toast = useToast();
const processingPassword = ref(false);

// Password form
const passwordForm = reactive({
    current_password: '',
    password: '',
    password_confirmation: '',
});

// Submit password change
const submitPasswordChange = () => {
    processingPassword.value = true;

    router.put('/profile/password', passwordForm, {
        preserveScroll: true,
        onSuccess: () => {
            // Reset form
            passwordForm.current_password = '';
            passwordForm.password = '';
            passwordForm.password_confirmation = '';

            toast.add({
                severity: 'success',
                summary: 'Thành công',
                detail: 'Đổi mật khẩu thành công!',
                life: 3000
            });
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Lỗi',
                detail: 'Có lỗi xảy ra khi đổi mật khẩu!',
                life: 3000
            });
        },
        onFinish: () => {
            processingPassword.value = false;
        }
    });
};
</script>

<style scoped>
.metric-card :deep(.p-card-content) {
    padding: 1.25rem;
}
</style>
