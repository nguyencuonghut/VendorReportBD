<template>
    <Head title="Auto Backup Configuration" />

    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold">Auto Backup</h1>
            </div>
            <Button
                @click="showCreateModal = true"
                icon="pi pi-plus"
                :label="'Tạo Backup Mới'"
                class="p-button-primary w-full sm:w-auto"
                size="small"
            />
        </div>

            <!-- Content -->
            <div>
                <!-- Stats Cards -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-6 mb-6 sm:mb-8">
                    <Card class="hover:shadow-md transition-shadow">
                        <template #content>
                            <div class="flex flex-col sm:flex-row sm:items-center">
                                <div class="p-2 bg-green-100 rounded-lg mb-2 sm:mb-0 self-start">
                                    <i class="pi pi-check-circle text-green-600 text-xl sm:text-2xl"></i>
                                </div>
                                <div class="sm:ml-4">
                                    <p class="text-xs sm:text-sm font-medium text-muted-color">Hoạt động</p>
                                    <p class="text-xl sm:text-2xl font-semibold">{{ activeConfigs }}</p>
                                </div>
                            </div>
                        </template>
                    </Card>

                    <Card class="hover:shadow-md transition-shadow">
                        <template #content>
                            <div class="flex flex-col sm:flex-row sm:items-center">
                                <div class="p-2 bg-blue-100 rounded-lg mb-2 sm:mb-0 self-start">
                                    <i class="pi pi-cloud-download text-blue-600 text-xl sm:text-2xl"></i>
                                </div>
                                <div class="sm:ml-4">
                                    <p class="text-xs sm:text-sm font-medium text-muted-color">Tổng Backup gần đây</p>
                                    <p class="text-xl sm:text-2xl font-semibold">{{ totalBackups }}</p>
                                </div>
                            </div>
                        </template>
                    </Card>

                    <Card class="hover:shadow-md transition-shadow">
                        <template #content>
                            <div class="flex flex-col sm:flex-row sm:items-center">
                                <div class="p-2 bg-yellow-100 rounded-lg mb-2 sm:mb-0 self-start">
                                    <i class="pi pi-clock text-yellow-600 text-xl sm:text-2xl"></i>
                                </div>
                                <div class="sm:ml-4">
                                    <p class="text-xs sm:text-sm font-medium text-muted-color">Backup Cuối</p>
                                    <p class="text-xs sm:text-sm font-semibold truncate">{{ lastBackupTime }}</p>
                                </div>
                            </div>
                        </template>
                    </Card>

                    <Card class="hover:shadow-md transition-shadow">
                        <template #content>
                            <div class="flex flex-col sm:flex-row sm:items-center">
                                <div class="p-2 bg-purple-100 rounded-lg mb-2 sm:mb-0 self-start">
                                    <i class="pi pi-google text-purple-600 text-xl sm:text-2xl"></i>
                                </div>
                                <div class="sm:ml-4">
                                    <p class="text-xs sm:text-sm font-medium text-muted-color">Google Drive</p>
                                    <p class="text-xs sm:text-sm font-semibold" :class="googleDriveConnected ? 'text-green-600' : 'text-red-600'">
                                        {{ googleDriveConnected ? `${googleDriveConfigsCount} cấu hình` : 'Chưa có cấu hình' }}
                                    </p>
                                </div>
                            </div>
                        </template>
                    </Card>
                </div>

                <!-- Backup Configurations -->
                <Card>
                    <template #title>
                        <div class="flex items-center justify-between">
                            <h5 class="font-bold">Cấu hình Backup</h5>
                        </div>
                    </template>
                    <template #content>
                        <div v-if="configurations.length === 0" class="text-center py-12">
                            <i class="pi pi-users text-6xl text-muted-color mb-4"></i>
                            <h3 class="mt-2 text-sm font-medium">Chưa có cấu hình backup</h3>
                            <p class="mt-1 text-sm text-muted-color">Bắt đầu bằng cách tạo cấu hình backup đầu tiên.</p>
                            <div class="mt-6">
                                <Button
                                    @click="showCreateModal = true"
                                    label="Tạo Backup Đầu Tiên"
                                    class="p-button-primary"
                                />
                            </div>
                        </div>

                        <div v-else class="space-y-4 sm:space-y-6">
                            <div v-for="config in configurations" :key="config.id" class="border-b border-surface-border pb-4 sm:pb-6 last:border-b-0">
                                <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 mb-2">
                                            <h3 class="text-base sm:text-lg font-medium truncate">{{ config.name }}</h3>
                                            <div class="flex flex-wrap gap-2">
                                                <Tag
                                                    :value="config.is_active ? 'Hoạt động' : 'Tạm dừng'"
                                                    :severity="config.is_active ? 'success' : 'danger'"
                                                    class="text-xs"
                                                />
                                                <Tag
                                                    v-if="config.google_drive_enabled && config.google_drive_config?.folder_name"
                                                    :value="`📁 ${config.google_drive_config.folder_name}`"
                                                    severity="info"
                                                    icon="pi pi-google"
                                                    class="text-xs max-w-[250px] truncate"
                                                />
                                                <Tag
                                                    v-else-if="config.google_drive_enabled"
                                                    value="Google Drive (chưa chọn folder)"
                                                    severity="warn"
                                                    icon="pi pi-google"
                                                    class="text-xs"
                                                />
                                            </div>
                                        </div>
                                        <p class="text-xs sm:text-sm text-muted-color mb-2 truncate">{{ config.schedule_description }}</p>
                                        <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-4 text-xs sm:text-sm text-muted-color">
                                            <span class="truncate">Lần cuối: {{ formatDate(config.last_run_at) }}</span>
                                            <span class="truncate">Tiếp theo: {{ formatDate(config.next_run_at) }}</span>
                                            <span class="truncate">Email: {{ config.notification_emails.length }} người nhận</span>
                                            <span v-if="config.google_drive_enabled && config.google_drive_config?.folder_name" class="text-blue-600 truncate">
                                                <i class="pi pi-google mr-1"></i>
                                                Folder: {{ config.google_drive_config.folder_name }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="flex flex-col sm:flex-row gap-2 flex-shrink-0">
                                        <Button
                                            @click="runBackup(config)"
                                            :disabled="runningBackup === config.id"
                                            :loading="runningBackup === config.id"
                                            label="Chạy ngay"
                                            icon="pi pi-play"
                                            class="p-button-success"
                                            size="small"
                                        />
                                        <Button
                                            @click="editConfig(config)"
                                            label="Sửa"
                                            icon="pi pi-pencil"
                                            severity="warn"
                                            size="small"
                                        />
                                        <Button
                                            @click="toggleConfig(config)"
                                            :label="config.is_active ? 'Tạm dừng' : 'Kích hoạt'"
                                            :icon="config.is_active ? 'pi pi-pause' : 'pi pi-play'"
                                            severity="contrast"
                                            size="small"
                                        />
                                        <Button
                                            @click="deleteConfig(config)"
                                            label="Xóa"
                                            icon="pi pi-trash"
                                            severity="danger"
                                            size="small"
                                        />
                                    </div>
                                </div>

                                <!-- Recent Logs -->
                                <div v-if="config.logs && config.logs.length > 0" class="mt-4">
                                    <h4 class="text-xs sm:text-sm font-medium mb-2">Lịch sử gần đây</h4>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                        <div
                                            v-for="log in config.logs.slice(0, 3)"
                                            :key="log.id"
                                            class="flex items-center gap-2 p-2 surface-ground border border-surface-border rounded text-xs sm:text-sm min-w-0"
                                        >
                                            <div
                                                :class="{
                                                    'w-2 h-2 rounded-full flex-shrink-0': true,
                                                    'bg-green-500': log.status === 'success',
                                                    'bg-red-500': log.status === 'failed',
                                                    'bg-blue-500': log.status === 'running'
                                                }"
                                            ></div>
                                            <span class="text-muted-color truncate flex-1">{{ formatDate(log.started_at) }}</span>
                                            <span v-if="log.formatted_file_size" class="text-muted-color text-xs flex-shrink-0">{{ log.formatted_file_size }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Create/Edit Backup Modal -->
            <BackupConfigModal
                :show="showCreateModal || showEditModal"
                :config="editingConfig"
                @close="closeModal"
                @saved="handleConfigSaved"
            />

        <!-- Google Drive Folder Picker Modal -->
        <GoogleDriveFolderPicker
            :show="showFolderPicker"
            @close="showFolderPicker = false"
            @selected="handleFolderSelected"
        />
    </div>

    <!-- Toast Messages -->
    <Toast />
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import { useForm, router } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Tag from 'primevue/tag'
import Toast from 'primevue/toast'
import BackupConfigModal from '@/Components/BackupConfigModal.vue'
import GoogleDriveFolderPicker from '@/Components/GoogleDriveFolderPicker.vue'

// Toast setup
const toast = useToast()

// Props
const props = defineProps({
    configurations: {
        type: Array,
        default: () => []
    }
})

// Reactive data
const showCreateModal = ref(false)
const showEditModal = ref(false)
const showFolderPicker = ref(false)
const editingConfig = ref(null)
const runningBackup = ref(null)

// Computed properties
const activeConfigs = computed(() =>
    props.configurations.filter(config => config.is_active).length
)

const totalBackups = computed(() =>
    props.configurations.reduce((total, config) => total + (config.logs?.length || 0), 0)
)

const lastBackupTime = computed(() => {
    const allLogs = props.configurations.flatMap(config => config.logs || [])
    if (allLogs.length === 0) return 'Chưa có'

    const latestLog = allLogs.sort((a, b) => new Date(b.started_at) - new Date(a.started_at))[0]
    return formatDate(latestLog.started_at)
})

const googleDriveConnected = computed(() => {
    return props.configurations.some(config =>
        config.google_drive_enabled &&
        config.google_drive_config?.folder_name
    )
})

const googleDriveConfigsCount = computed(() => {
    return props.configurations.filter(config =>
        config.google_drive_enabled &&
        config.google_drive_config?.folder_name
    ).length
})

// Methods
const formatDate = (dateString) => {
    if (!dateString) return 'Chưa có'
    try {
        const date = new Date(dateString)
        if (isNaN(date.getTime())) return 'Không hợp lệ'

        return date.toLocaleDateString('vi-VN', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        })
    } catch {
        return 'Không hợp lệ'
    }
}

const closeModal = () => {
    showCreateModal.value = false
    showEditModal.value = false
    editingConfig.value = null
}

const editConfig = (config) => {
    editingConfig.value = config
    showEditModal.value = true
}

const handleConfigSaved = async () => {
    closeModal()
    // Refetch configurations from server instead of reloading page
    try {
        await router.reload()
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Lỗi reload',
            detail: 'Không thể cập nhật danh sách backup!',
            life: 5000
        })
    }
}

const runBackup = async (config) => {
    runningBackup.value = config.id

    try {
        const form = useForm({})
        await form.post(`/backup/configurations/${config.id}/run`)

        // Show success toast
        toast.add({
            severity: 'success',
            summary: 'Backup thành công',
            detail: `Backup cho "${config.name}" đã được thực hiện thành công!`,
            life: 5000
        })

        // Reload page to refresh backup logs
        setTimeout(() => {
            window.location.reload()
        }, 1000)

    } catch (error) {
        console.error('Backup failed:', error)

        // Show error toast
        toast.add({
            severity: 'error',
            summary: 'Backup thất bại',
            detail: error.response?.data?.message || `Có lỗi xảy ra khi thực hiện backup cho "${config.name}"!`,
            life: 5000
        })
    } finally {
        runningBackup.value = null
    }
}

const toggleConfig = async (config) => {
    try {
        const form = useForm({
            is_active: !config.is_active
        })
        await form.patch(`/backup/configurations/${config.id}`)

        config.is_active = !config.is_active

    } catch (error) {
        console.error('Toggle config failed:', error)
    }
}

const deleteConfig = async (config) => {
    // Confirm deletion
    const confirmed = confirm(`Bạn có chắc chắn muốn xóa cấu hình backup "${config.name}"?\n\nThao tác này sẽ xóa vĩnh viễn cấu hình và tất cả lịch sử backup liên quan.`)

    if (!confirmed) return

    try {
        const form = useForm({})
        await form.delete(`/backup/configurations/${config.id}`)

        // Show success toast
        toast.add({
            severity: 'success',
            summary: 'Xóa thành công',
            detail: `Đã xóa cấu hình backup "${config.name}"`,
            life: 3000
        })

        // Reload page to update the list
        setTimeout(() => {
            window.location.reload()
        }, 1000)

    } catch (error) {
        console.error('Delete config failed:', error)

        // Show error toast
        toast.add({
            severity: 'error',
            summary: 'Xóa thất bại',
            detail: error.response?.data?.message || `Có lỗi xảy ra khi xóa cấu hình "${config.name}"!`,
            life: 5000
        })
    }
}

const handleFolderSelected = (folder) => {
    showFolderPicker.value = false
    // Handle folder selection
    console.log('Selected folder:', folder)
}

// Mounted
onMounted(() => {
    // Google Drive connection status is now computed from configurations
})
</script>
