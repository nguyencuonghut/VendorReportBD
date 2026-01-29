<template>
    <div v-if="show" class="fixed inset-0 z-[9999] overflow-y-auto">
        <!-- Overlay -->
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 z-[9998]" @click="$emit('close')"></div>

        <!-- Modal Container -->
        <div class="fixed inset-0 z-[9999] flex items-start justify-center p-4" style="padding-top: 100px; padding-bottom: 40px;">
            <!-- Modal -->
            <div class="relative w-full max-w-2xl px-6 py-4 overflow-hidden text-left align-middle transition-all transform bg-white shadow-xl rounded-lg max-h-full overflow-y-auto">
                <!-- Header -->
                <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">
                        {{ config ? 'Sửa cấu hình Backup' : 'Tạo cấu hình Backup mới' }}
                    </h3>
                    <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Content -->
                <form @submit.prevent="save" class="mt-6">
                    <!-- Basic Info -->
                    <div class="space-y-6">
                        <!-- Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tên cấu hình</label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                placeholder="VD: Daily Database Backup"
                            >
                            <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name }}</p>
                        </div>

                        <!-- Schedule -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Lịch trình</label>
                            <div class="space-y-3">
                                <label class="flex items-center">
                                    <input
                                        v-model="form.schedule.type"
                                        value="daily"
                                        type="radio"
                                        class="form-radio h-4 w-4 text-blue-600"
                                    >
                                    <span class="ml-2 text-sm text-gray-700">Hằng ngày</span>
                                </label>
                                <label class="flex items-center">
                                    <input
                                        v-model="form.schedule.type"
                                        value="weekly"
                                        type="radio"
                                        class="form-radio h-4 w-4 text-blue-600"
                                    >
                                    <span class="ml-2 text-sm text-gray-700">Hằng tuần</span>
                                </label>
                                <label class="flex items-center">
                                    <input
                                        v-model="form.schedule.type"
                                        value="monthly"
                                        type="radio"
                                        class="form-radio h-4 w-4 text-blue-600"
                                    >
                                    <span class="ml-2 text-sm text-gray-700">Hằng tháng</span>
                                </label>
                            </div>

                            <!-- Time Selection -->
                            <div class="mt-3 flex gap-4">
                                <div v-if="form.schedule.type === 'weekly'" class="flex-1">
                                    <label class="block text-xs text-gray-600">Ngày trong tuần</label>
                                    <select v-model="form.schedule.day_of_week" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm">
                                        <option value="1">Thứ 2</option>
                                        <option value="2">Thứ 3</option>
                                        <option value="3">Thứ 4</option>
                                        <option value="4">Thứ 5</option>
                                        <option value="5">Thứ 6</option>
                                        <option value="6">Thứ 7</option>
                                        <option value="0">Chủ nhật</option>
                                    </select>
                                </div>

                                <div v-if="form.schedule.type === 'monthly'" class="flex-1">
                                    <label class="block text-xs text-gray-600">Ngày trong tháng</label>
                                    <input
                                        v-model="form.schedule.day_of_month"
                                        type="number"
                                        min="1"
                                        max="28"
                                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm"
                                    >
                                </div>

                                <div class="flex-1">
                                    <label class="block text-xs text-gray-600">Thời gian</label>
                                    <input
                                        v-model="form.schedule.time"
                                        type="time"
                                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm"
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- Backup Options -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nội dung backup</label>
                            <div class="space-y-2">
                                <label class="flex items-center">
                                    <input
                                        v-model="form.backup_options.database"
                                        type="checkbox"
                                        class="form-checkbox h-4 w-4 text-blue-600"
                                    >
                                    <span class="ml-2 text-sm text-gray-700">Database</span>
                                </label>
                                <label class="flex items-center">
                                    <input
                                        v-model="form.backup_options.uploaded_files"
                                        type="checkbox"
                                        class="form-checkbox h-4 w-4 text-blue-600"
                                    >
                                    <span class="ml-2 text-sm text-gray-700">Files đã upload</span>
                                </label>
                                <label class="flex items-center">
                                    <input
                                        v-model="form.backup_options.env_file"
                                        type="checkbox"
                                        class="form-checkbox h-4 w-4 text-blue-600"
                                    >
                                    <span class="ml-2 text-sm text-gray-700">File .env</span>
                                </label>
                            </div>
                        </div>

                        <!-- Google Drive -->
                        <div>
                            <div class="flex items-center justify-between">
                                <label class="block text-sm font-medium text-gray-700">Google Drive</label>
                                <label class="flex items-center">
                                    <input
                                        v-model="form.google_drive_enabled"
                                        type="checkbox"
                                        class="form-checkbox h-4 w-4 text-blue-600"
                                    >
                                    <span class="ml-2 text-sm text-gray-700">Upload lên Google Drive</span>
                                </label>
                            </div>

                            <div v-if="form.google_drive_enabled" class="mt-3 p-4 bg-blue-50 rounded-lg">
                                <div v-if="!googleDriveConfig" class="text-center">
                                    <svg class="mx-auto h-8 w-8 text-blue-500 mb-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                                    </svg>
                                    <p class="text-sm text-blue-700 mb-3">Kết nối với Google Drive để lưu trữ backup</p>
                                    <button
                                        @click="connectGoogleDrive"
                                        type="button"
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium"
                                    >
                                        Kết nối Google Drive
                                    </button>
                                </div>

                                <div v-else class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"></path>
                                            </svg>
                                            <span class="text-sm text-green-700">
                                                Đã kết nối: <strong>{{ googleDriveConfig.folder_name }}</strong>
                                            </span>
                                        </div>
                                        <button
                                            @click="disconnectGoogleDrive"
                                            type="button"
                                            class="text-red-600 hover:text-red-800 text-sm"
                                        >
                                            Ngắt kết nối
                                        </button>
                                    </div>

                                    <!-- Google Drive Actions -->
                                    <div class="flex justify-center gap-3">
                                        <!-- Test Connection Button -->
                                        <button
                                            @click="testGoogleDriveConnection"
                                            type="button"
                                            :disabled="testingConnection"
                                            class="flex items-center gap-2 bg-blue-100 hover:bg-blue-200 disabled:opacity-50 text-blue-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                                        >
                                            <svg v-if="testingConnection" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            {{ testingConnection ? 'Đang test...' : 'Test kết nối' }}
                                        </button>

                                        <!-- Browse Google Drive Folder Button -->
                                        <button
                                            @click="showGoogleDriveFolderPicker"
                                            type="button"
                                            class="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path>
                                            </svg>
                                            Chọn thư mục Google Drive
                                        </button>
                                    </div>

                                    <!-- Test Result -->
                                    <div v-if="testResult" class="text-center text-sm" :class="testResult.success ? 'text-green-600' : 'text-red-600'">
                                        {{ testResult.message }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Notification Emails -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email thông báo</label>
                            <div class="space-y-2">
                                <div v-for="(email, index) in form.notification_emails" :key="index" class="flex gap-2">
                                    <input
                                        v-model="form.notification_emails[index]"
                                        type="email"
                                        required
                                        class="flex-1 border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="email@example.com"
                                    >
                                    <button
                                        v-if="form.notification_emails.length > 1"
                                        @click="removeEmail(index)"
                                        type="button"
                                        class="text-red-600 hover:text-red-800"
                                    >
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"></path>
                                        </svg>
                                    </button>
                                </div>
                                <button
                                    @click="addEmail"
                                    type="button"
                                    class="text-blue-600 hover:text-blue-800 text-sm flex items-center gap-1"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    Thêm email
                                </button>
                            </div>
                        </div>

                        <!-- Retention -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Lưu trữ backup</label>
                            <div class="mt-1 flex items-center gap-2">
                                <input
                                    v-model="form.retention_days"
                                    type="number"
                                    min="1"
                                    max="365"
                                    class="w-20 border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm"
                                >
                                <span class="text-sm text-gray-600">ngày</span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Backup cũ sẽ được xóa tự động sau thời gian này</p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex justify-end items-center mt-8 pt-4 border-t border-gray-200">
                        <!-- Standard buttons only -->
                        <div class="flex gap-3">
                            <button
                                @click="$emit('close')"
                                type="button"
                                class="bg-white hover:bg-gray-50 text-gray-900 border border-gray-300 px-4 py-2 rounded-lg font-medium"
                            >
                                Hủy
                            </button>
                            <button
                                type="submit"
                                :disabled="processing"
                                class="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg font-medium"
                            >
                                {{ processing ? 'Đang lưu...' : (config ? 'Cập nhật' : 'Tạo mới') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Google Drive Folder Picker Modal -->
    <GoogleDriveFolderPicker
        :show="showFolderPicker"
        @close="showFolderPicker = false"
        @selected="handleFolderSelected"
    />
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { useForm } from '@inertiajs/vue3'
import GoogleDriveFolderPicker from './GoogleDriveFolderPicker.vue'

// Props & Emits
const props = defineProps({
    show: Boolean,
    config: Object
})

const emit = defineEmits(['close', 'saved'])

// Reactive data
const googleDriveConfig = ref(null)
const processing = ref(false)
const errors = ref({})
const testingConnection = ref(false)
const testResult = ref(null)
const connectingGoogleDrive = ref(false)
const showFolderPicker = ref(false)

// Helper function to get CSRF token safely
const getCsrfToken = () => {
    return document.querySelector('meta[name="csrf-token"]')?.content ||
           document.querySelector('input[name="_token"]')?.value ||
           window.Laravel?.csrfToken
}

// Form data
const form = ref({
    name: '',
    schedule: {
        type: 'daily',
        time: '02:00',
        day_of_week: 1,
        day_of_month: 1
    },
    backup_options: {
        database: true,
        uploaded_files: true,
        env_file: false
    },
    google_drive_enabled: false,
    notification_emails: [''],
    retention_days: 7
})

// Helper function to clear Google Drive session (moved before watcher)
const clearGoogleDriveSession = async () => {
    try {
        const csrfToken = getCsrfToken()
        if (!csrfToken) {
            return
        }

        await fetch('/api/google-drive/disconnect', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        })

        googleDriveConfig.value = null
        form.value.google_drive_enabled = false
    } catch (error) {
        console.error('Failed to clear Google Drive session:', error)
    }
}

// Watch for config changes
watch(() => props.config, (newConfig) => {
    if (newConfig) {
        form.value = {
            name: newConfig.name,
            schedule: newConfig.schedule || form.value.schedule,
            backup_options: newConfig.backup_options || form.value.backup_options,
            google_drive_enabled: newConfig.google_drive_enabled || false,
            notification_emails: newConfig.notification_emails || [''],
            retention_days: newConfig.retention_days || 7
        }
        googleDriveConfig.value = newConfig.google_drive_config
    } else {
        // Reset form for new config - also clear Google Drive session
        form.value = {
            name: '',
            schedule: {
                type: 'daily',
                time: '02:00',
                day_of_week: 1,
                day_of_month: 1
            },
            backup_options: {
                database: true,
                uploaded_files: true,
                env_file: false
            },
            google_drive_enabled: false,
            notification_emails: [''],
            retention_days: 7
        }
        googleDriveConfig.value = null

        // Clear Google Drive session when creating new config
        clearGoogleDriveSession()
    }
}, { immediate: true })

// Methods
const addEmail = () => {
    form.value.notification_emails.push('')
}

const removeEmail = (index) => {
    form.value.notification_emails.splice(index, 1)
}

const connectGoogleDrive = async () => {
    connectingGoogleDrive.value = true
    try {
        const csrfToken = getCsrfToken()
        if (!csrfToken) {
            throw new Error('CSRF token not found. Please refresh the page.')
        }

        const response = await fetch('/auth/google-drive/connect', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        })

        const data = await response.json()

        if (data.success) {
            // Open popup for Google OAuth
            const popup = window.open(data.auth_url, 'google-oauth', 'width=500,height=600')

            // Server-side session polling approach

            const pollInterval = setInterval(async () => {
                try {
                    const csrfToken = getCsrfToken()
                    if (!csrfToken) {
                        console.error('No CSRF token for polling')
                        return
                    }

                    const response = await fetch('/api/google-drive/status', {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        }
                    })

                    if (response.ok) {
                        const data = await response.json()

                        if (data.success && data.config) {
                            console.log('OAuth successful! Google Drive connected.')
                            clearInterval(pollInterval)

                            // Skip popup.close() entirely to avoid COOP policy errors
                            // Popup will close naturally when user returns to main window

                            // Update state
                            googleDriveConfig.value = data.config
                            form.value.google_drive_enabled = true
                            connectingGoogleDrive.value = false

                            // Auto-open folder picker if connection confirmed
                            setTimeout(() => {
                                showGoogleDriveFolderPicker()
                            }, 1000)

                            return
                        }
                    }
                } catch (error) {
                    console.error('OAuth polling error:', error)
                }
            }, 1000) // Poll every second

            // Cleanup after 60 seconds
            setTimeout(() => {
                clearInterval(pollInterval)
                if (connectingGoogleDrive.value) {
                    console.log('OAuth timeout - cleaning up')
                    connectingGoogleDrive.value = false
                }


                // Skip popup.close() on timeout to avoid COOP policy errors
                // User can close popup manually if needed
            }, 60000)
        }
    } catch (error) {
        console.error('Failed to connect Google Drive:', error)
        connectingGoogleDrive.value = false
    }
}

const disconnectGoogleDrive = async () => {
    try {
        const csrfToken = getCsrfToken()
        if (!csrfToken) {
            throw new Error('CSRF token not found. Please refresh the page.')
        }

        await fetch('/api/google-drive/disconnect', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        })

        googleDriveConfig.value = null
        form.value.google_drive_enabled = false
    } catch (error) {
        console.error('Failed to disconnect Google Drive:', error)
    }
}

const testGoogleDriveConnection = async () => {
    if (!googleDriveConfig.value) {
        testResult.value = { success: false, message: 'Chưa kết nối Google Drive' }
        return
    }

    testingConnection.value = true
    testResult.value = null

    try {
        const csrfToken = getCsrfToken()
        if (!csrfToken) {
            throw new Error('CSRF token not found. Please refresh the page.')
        }

        const response = await fetch('/backup/configurations/test-google-drive', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        })

        const data = await response.json()
        testResult.value = data

        // Auto hide result after 3 seconds
        setTimeout(() => {
            testResult.value = null
        }, 3000)

    } catch (error) {
        testResult.value = { success: false, message: 'Lỗi kết nối: ' + error.message }
    } finally {
        testingConnection.value = false
    }
}

const checkGoogleDriveConnection = async () => {
    try {
        const csrfToken = getCsrfToken()
        if (!csrfToken) {
            throw new Error('CSRF token not found. Please refresh the page.')
        }

        const response = await fetch('/api/google-drive/status', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        })

        if (response.ok) {
            const data = await response.json()
            if (data.success && data.config) {
                googleDriveConfig.value = data.config
                form.value.google_drive_enabled = true
            } else {
                googleDriveConfig.value = null
                form.value.google_drive_enabled = false
            }
        } else {
            console.error('Failed to fetch Google Drive status. Response status:', response.status)
            googleDriveConfig.value = null
            form.value.google_drive_enabled = false
        }
    } catch (error) {
        console.error('Failed to check Google Drive connection:', error)
        googleDriveConfig.value = null
        form.value.google_drive_enabled = false
    }
}

const save = async () => {
    processing.value = true
    errors.value = {}

    try {
        const formData = {
            ...form.value,
            google_drive_config: googleDriveConfig.value
        }

        const formInstance = useForm(formData)

        if (props.config) {
            await formInstance.put(`/backup/configurations/${props.config.id}`)
        } else {
            await formInstance.post('/backup/configurations')
        }

        emit('saved')

    } catch (error) {
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors
        }
    } finally {
        processing.value = false
    }
}

const showGoogleDriveFolderPicker = () => {
    showFolderPicker.value = true
}

// Helper function for token exchange
const handleTokenExchange = async (code, state) => {
    try {
        const csrfToken = getCsrfToken()
        if (!csrfToken) {
            throw new Error('CSRF token not found. Please refresh the page.')
        }

        console.log('Exchanging token with code:', code)

        const response = await fetch('/auth/google-drive/exchange-token', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                code: code,
                state: state || ''
            })
        })

        const result = await response.json()

        if (result.success) {
            // Update local state immediately if config is returned
            if (result.config) {
                googleDriveConfig.value = result.config
                form.value.google_drive_enabled = true
            }

            // Also check connection status from server
            setTimeout(async () => {
                await checkGoogleDriveConnection()

                // Auto-open folder picker if connection is confirmed
                if (googleDriveConfig.value) {
                    showGoogleDriveFolderPicker()
                }
            }, 1500) // Increase delay to ensure session is saved
        } else {
            console.error('Failed to exchange token:', result.message || result)
        }
    } catch (error) {
        console.error('Token exchange error:', error)
    }

    connectingGoogleDrive.value = false
}

const handleFolderSelected = (folder) => {
    showFolderPicker.value = false
    if (folder) {
        googleDriveConfig.value = {
            folder_id: folder.id,
            folder_name: folder.name,
            connected: true
        }
        form.value.google_drive_enabled = true
    }
}

// Mounted
onMounted(() => {
    // Only check Google Drive connection for existing configs
    // For new configs, start fresh
    if (props.config) {
        checkGoogleDriveConnection()
    }
})
</script>
