<template>
    <div v-if="show" class="fixed inset-0 z-[10000] overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <!-- Overlay -->
            <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 z-[9999]" @click="$emit('close')"></div>

            <!-- Modal -->
            <div class="inline-block w-full max-w-md px-6 py-4 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-xl rounded-lg z-[10000] relative">
                <!-- Header -->
                <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Chọn thư mục Google Drive</h3>
                    <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Content -->
                <div class="mt-4">
                    <!-- Breadcrumb -->
                    <div v-if="breadcrumb.length > 0" class="flex items-center gap-2 mb-4 text-sm text-gray-600">
                        <button
                            @click="navigateToFolder(null)"
                            class="hover:text-blue-600"
                        >
                            My Drive
                        </button>
                        <template v-for="(crumb, index) in breadcrumb" :key="crumb.id">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"></path>
                            </svg>
                            <button
                                @click="navigateToFolder(crumb.id)"
                                class="hover:text-blue-600"
                            >
                                {{ crumb.name }}
                            </button>
                        </template>
                    </div>

                    <!-- Create New Folder -->
                    <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                        <div class="flex gap-2">
                            <input
                                v-model="newFolderName"
                                type="text"
                                placeholder="Tên thư mục mới"
                                class="flex-1 border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                @keyup.enter="createFolder"
                            >
                            <button
                                @click="createFolder"
                                :disabled="!newFolderName.trim() || creatingFolder"
                                class="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white px-3 py-2 rounded-md text-sm font-medium"
                            >
                                {{ creatingFolder ? 'Tạo...' : 'Tạo' }}
                            </button>
                        </div>
                    </div>

                    <!-- Folders List -->
                    <div class="max-h-64 overflow-y-auto">
                        <div v-if="loading" class="text-center py-8">
                            <svg class="animate-spin -ml-1 mr-3 h-6 w-6 text-blue-600 mx-auto" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <p class="text-sm text-gray-600 mt-2">Đang tải thư mục...</p>
                        </div>

                        <div v-else-if="folders.length === 0" class="text-center py-8">
                            <svg class="mx-auto h-8 w-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                            </svg>
                            <p class="text-sm text-gray-600">Không có thư mục nào</p>
                        </div>

                        <div v-else class="space-y-1">
                            <button
                                v-for="folder in folders"
                                :key="folder.id"
                                @click="selectFolder(folder)"
                                @dblclick="navigateToFolder(folder.id)"
                                class="w-full flex items-center gap-3 px-3 py-2 text-left hover:bg-blue-50 rounded-lg transition-colors"
                                :class="{ 'bg-blue-100': selectedFolder?.id === folder.id }"
                            >
                                <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path>
                                </svg>
                                <span class="flex-1 text-sm text-gray-900">{{ folder.name }}</span>
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Selected Folder Info -->
                    <div v-if="selectedFolder" class="mt-4 p-3 bg-green-50 rounded-lg">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"></path>
                            </svg>
                            <span class="text-sm text-green-800">
                                Đã chọn: <strong>{{ selectedFolder.name }}</strong>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200">
                    <button
                        @click="$emit('close')"
                        class="bg-white hover:bg-gray-50 text-gray-900 border border-gray-300 px-4 py-2 rounded-lg font-medium"
                    >
                        Hủy
                    </button>
                    <button
                        @click="confirmSelection"
                        :disabled="!selectedFolder || selecting"
                        class="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg font-medium"
                    >
                        {{ selecting ? 'Đang chọn...' : 'Chọn thư mục' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'

// Props & Emits
const props = defineProps({
    show: Boolean
})

const emit = defineEmits(['close', 'selected'])

// Helper function to safely get CSRF token
const getCsrfToken = () => {
    return document.querySelector('meta[name="csrf-token"]')?.content ||
           document.querySelector('input[name="_token"]')?.value ||
           window.Laravel?.csrfToken ||
           null
}

// Reactive data
const folders = ref([])
const selectedFolder = ref(null)
const breadcrumb = ref([])
const currentFolderId = ref(null)
const loading = ref(false)
const creatingFolder = ref(false)
const selecting = ref(false)
const newFolderName = ref('')

// Methods
const loadFolders = async (parentId = null) => {
    loading.value = true

    try {
        const params = new URLSearchParams()
        if (parentId) {
            params.append('parent_id', parentId)
        }

        const csrfToken = getCsrfToken()
        if (!csrfToken) {
            throw new Error('CSRF token not found. Please refresh the page.')
        }

        const response = await fetch(`/api/google-drive/folders?${params}`, {
            headers: {
                'X-CSRF-TOKEN': csrfToken
            }
        })

        const data = await response.json()

        if (data.success) {
            folders.value = data.folders
        } else {
            console.error('Failed to load folders:', data.message)
            folders.value = []
        }

    } catch (error) {
        console.error('Error loading folders:', error)
        folders.value = []
    } finally {
        loading.value = false
    }
}

const navigateToFolder = (folderId) => {
    if (folderId === null) {
        // Navigate to root
        breadcrumb.value = []
        currentFolderId.value = null
    } else {
        // Navigate to subfolder
        const folder = folders.value.find(f => f.id === folderId)
        if (folder) {
            breadcrumb.value.push({
                id: folder.id,
                name: folder.name
            })
            currentFolderId.value = folderId
        }
    }

    selectedFolder.value = null
    loadFolders(currentFolderId.value)
}

const selectFolder = (folder) => {
    selectedFolder.value = folder
}

const createFolder = async () => {
    if (!newFolderName.value.trim()) return

    creatingFolder.value = true

    try {
        const csrfToken = getCsrfToken()
        if (!csrfToken) {
            throw new Error('CSRF token not found. Please refresh the page.')
        }

        const response = await fetch('/api/google-drive/create-folder', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                folder_name: newFolderName.value.trim(),
                parent_folder_id: currentFolderId.value
            })
        })

        const data = await response.json()

        if (data.success) {
            newFolderName.value = ''
            // Reload folders to show the new one
            loadFolders(currentFolderId.value)
        } else {
            alert('Không thể tạo thư mục: ' + data.message)
        }

    } catch (error) {
        console.error('Error creating folder:', error)
        alert('Có lỗi xảy ra khi tạo thư mục')
    } finally {
        creatingFolder.value = false
    }
}

const confirmSelection = async () => {
    if (!selectedFolder.value) return

    selecting.value = true

    try {
        const csrfToken = getCsrfToken()
        if (!csrfToken) {
            throw new Error('CSRF token not found. Please refresh the page.')
        }

        const response = await fetch('/api/google-drive/select-folder', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                folder_id: selectedFolder.value.id,
                folder_name: selectedFolder.value.name
            })
        })

        const data = await response.json()

        if (data.success) {
            emit('selected', {
                id: selectedFolder.value.id,
                name: selectedFolder.value.name,
                config: data.config
            })
        } else {
            alert('Không thể chọn thư mục: ' + data.message)
        }

    } catch (error) {
        console.error('Error selecting folder:', error)
        alert('Có lỗi xảy ra khi chọn thư mục')
    } finally {
        selecting.value = false
    }
}

// Watch for show prop changes
watch(() => props.show, (newShow) => {
    if (newShow) {
        // Reset state when modal opens
        folders.value = []
        selectedFolder.value = null
        breadcrumb.value = []
        currentFolderId.value = null
        newFolderName.value = ''

        // Load root folders
        loadFolders()
    }
})
</script>
