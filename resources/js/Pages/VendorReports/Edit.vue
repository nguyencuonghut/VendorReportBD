<template>
    <Head>
        <title>Chỉnh sửa phiếu: {{ reportData.title }}</title>
    </Head>

    <div class="card">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold">Chỉnh sửa phiếu</h2>
            <Tag v-if="reportData.status_label" :value="reportData.status_label" :severity="reportData.status_color" />
        </div>

        <form @submit.prevent="updateReport" class="flex flex-col gap-6">
            <!-- Tiêu đề phiếu -->
            <div>
                <label for="title" class="block font-bold mb-3">Tiêu đề phiếu <span class="text-red-500">*</span></label>
                <InputText
                    id="title"
                    v-model="form.title"
                    required
                    :invalid="submitted && !form.title || hasError('title')"
                    fluid
                    placeholder="Nhập tiêu đề phiếu đề nghị"
                />
                <small v-if="submitted && !form.title" class="text-red-500 block mt-1">Tiêu đề phiếu là bắt buộc</small>
                <small v-if="hasError('title')" class="p-error block mt-1">{{ getError('title') }}</small>
            </div>

            <!-- Loại quy trình -->
            <div>
                <label for="workflow_type" class="block font-bold mb-3">Loại quy trình <span class="text-red-500">*</span></label>
                <Select
                    id="workflow_type"
                    v-model="form.workflow_type"
                    :options="workflowTypeOptions"
                    optionLabel="label"
                    optionValue="value"
                    placeholder="Chọn loại quy trình phê duyệt"
                    :invalid="submitted && !form.workflow_type || hasError('workflow_type')"
                    fluid
                />
                <small v-if="submitted && !form.workflow_type" class="text-red-500 block mt-1">Loại quy trình là bắt buộc</small>
                <small v-if="hasError('workflow_type')" class="p-error block mt-1">{{ getError('workflow_type') }}</small>

                <!-- Workflow description -->
                <div v-if="form.workflow_type" class="mt-2 p-3 bg-blue-50 rounded border border-blue-200">
                    <p class="text-sm text-blue-800">
                        <i class="pi pi-info-circle mr-2"></i>
                        {{ getWorkflowDescription(form.workflow_type) }}
                    </p>
                </div>
            </div>

            <!-- Admin mua hàng -->
            <div>
                <label for="purchasing_admin_id" class="block font-bold mb-3">Admin mua hàng</label>
                <Select
                    id="purchasing_admin_id"
                    v-model="form.purchasing_admin_id"
                    :options="props.purchasingAdmins"
                    optionLabel="name"
                    optionValue="id"
                    placeholder="Chọn admin mua hàng"
                    :invalid="hasError('purchasing_admin_id')"
                    fluid
                    filter
                    showClear
                />
                <small class="text-gray-600 block mt-1">Chỉ để theo dõi, không tham gia vào quy trình phê duyệt</small>
                <small v-if="hasError('purchasing_admin_id')" class="p-error block mt-1">{{ getError('purchasing_admin_id') }}</small>
            </div>

            <!-- Ảnh báo cáo -->
            <div>
                <label class="block font-bold mb-3">Ảnh báo cáo <span class="text-red-500">*</span></label>

                <!-- Existing report image -->
                <div v-if="existingReportImage && !isFileMarkedForDeletion(existingReportImage.id)" class="mb-4">
                    <h4 class="font-semibold mb-2">Ảnh hiện tại:</h4>
                    <div class="border rounded-lg p-4 bg-gray-50 relative">
                        <Image
                            :src="`/vendor-reports/files/${existingReportImage.id}/view`"
                            :alt="existingReportImage.original_name"
                            preview
                            class="w-full"
                            imageClass="w-full object-contain max-h-[400px]"
                        />
                        <div class="mt-2 flex items-center justify-between">
                            <span class="text-sm text-gray-600">
                                {{ formatFileSize(existingReportImage.size) }}
                            </span>
                            <div class="flex gap-2">
                                <Button
                                    label="Mở trong tab mới"
                                    icon="pi pi-external-link"
                                    severity="secondary"
                                    text
                                    size="small"
                                    @click="viewFile(existingReportImage.id)"
                                />
                                <Button
                                    label="Xóa ảnh hiện tại"
                                    icon="pi pi-trash"
                                    severity="danger"
                                    text
                                    size="small"
                                    @click="markFileForDeletion(existingReportImage.id)"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upload new image -->
                <div
                    :key="editorKey"
                    ref="pasteAreaRef"
                    :contenteditable="isContentEditable"
                    class="p-inputtext p-component p-editor-container"
                    :class="{ 'has-content': imagePreviewSrc || (!showPlaceholder && pasteAreaRef?.innerText?.trim() !== '') }"
                    style="min-height: 150px; border: 1px solid var(--surface-300); padding: 1rem; cursor: text; overflow: hidden;"
                    @paste="handlePaste"
                    @drop.prevent="handleDrop"
                    @dragover.prevent
                    @focus="handleFocus"
                    @blur="handleBlur"
                >
                    <div v-if="!imagePreviewSrc && showPlaceholder" class="paste-content-wrapper">
                        <p class="placeholder-text">{{ existingReportImage ? 'Dán ảnh mới (Ctrl+V) để thay thế' : 'Dán ảnh (Ctrl+V) hoặc kéo thả ảnh vào đây.' }}</p>
                    </div>
                    <div v-else-if="imagePreviewSrc" class="paste-content-wrapper">
                        <img :src="imagePreviewSrc" alt="Image Preview" class="pasted-image-preview" />
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-3" v-if="imagePreviewSrc">
                    <i class="pi pi-image text-xl"></i>
                    <span class="font-medium">Ảnh mới đã chọn</span>
                    <span v-if="imageFile" class="text-color-secondary">{{ (imageFile.size / 1024).toFixed(2) }} KB ({{ imageFile.name }})</span>
                    <Button label="Xóa ảnh mới" icon="pi pi-times" severity="danger" text size="small" class="ml-auto" @click="removeImage" />
                </div>
                <small v-if="hasError('report_image')" class="p-error block mt-1">{{ getError('report_image') }}</small>
            </div>

            <!-- File báo giá -->
            <div>
                <label class="block font-bold mb-3">File báo giá <span class="text-red-500">*</span></label>

                <!-- Existing quotation files -->
                <div v-if="existingQuotationFilesFiltered.length > 0" class="mb-4">
                    <h4 class="font-semibold mb-2">File hiện tại:</h4>
                    <div class="space-y-2">
                        <div v-for="file in existingQuotationFilesFiltered" :key="file.id" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center space-x-3">
                                <i :class="getFileIconByName(file.original_name)" class="text-xl"></i>
                                <div>
                                    <p class="font-medium text-sm">{{ file.original_name }}</p>
                                    <p class="text-xs text-gray-500">{{ formatFileSize(file.size) }}</p>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <Button icon="pi pi-eye" text severity="info" size="small" @click="viewFile(file.id)" />
                                <Button icon="pi pi-download" text severity="secondary" size="small" @click="downloadFile(file.id)" />
                                <Button icon="pi pi-trash" text severity="danger" size="small" @click="markFileForDeletion(file.id)" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upload new files -->
                <div
                    class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center cursor-pointer hover:border-blue-400 transition-colors"
                    @drop.prevent="handleQuotationFilesDrop"
                    @dragover.prevent
                    @click="$refs.quotationFilesInput.click()"
                >
                    <i class="pi pi-cloud-upload text-4xl text-green-400 mb-2"></i>
                    <p class="text-gray-600 mb-1">{{ existingQuotationFiles.length > 0 ? 'Thêm file báo giá mới' : 'Kéo thả file báo giá vào đây hoặc click để chọn' }}</p>
                    <p class="text-sm text-gray-500">Hỗ trợ: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG</p>
                    <input
                        type="file"
                        ref="quotationFilesInput"
                        @change="handleQuotationFilesSelect"
                        multiple
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                        class="hidden"
                    />
                </div>
                <div v-if="uploadedQuotationFiles.length > 0" class="mt-4">
                    <h4 class="font-semibold mb-2">File mới đã chọn:</h4>
                    <div class="space-y-2">
                        <div v-for="(file, index) in uploadedQuotationFiles" :key="index" class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                            <div class="flex items-center space-x-3">
                                <i :class="getFileIcon(file.type)" class="text-xl"></i>
                                <div>
                                    <p class="font-medium text-sm">{{ file.name }}</p>
                                    <p class="text-xs text-gray-500">{{ formatFileSize(file.size) }}</p>
                                </div>
                            </div>
                            <Button icon="pi pi-times" text severity="danger" size="small" @click="removeQuotationFile(index)" />
                        </div>
                    </div>
                </div>
                <small v-if="hasError('quotation_files')" class="p-error block mt-1">{{ getError('quotation_files') }}</small>
            </div>

            <!-- File BOQ -->
            <div>
                <label class="block font-bold mb-3">File Đề nghị/BOQ</label>

                <!-- Existing BOQ files -->
                <div v-if="existingBoqFilesFiltered.length > 0" class="mb-4">
                    <h4 class="font-semibold mb-2">File hiện tại:</h4>
                    <div class="space-y-2">
                        <div v-for="file in existingBoqFilesFiltered" :key="file.id" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center space-x-3">
                                <i :class="getFileIconByName(file.original_name)" class="text-xl"></i>
                                <div>
                                    <p class="font-medium text-sm">{{ file.original_name }}</p>
                                    <p class="text-xs text-gray-500">{{ formatFileSize(file.size) }}</p>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <Button icon="pi pi-eye" text severity="info" size="small" @click="viewFile(file.id)" />
                                <Button icon="pi pi-download" text severity="secondary" size="small" @click="downloadFile(file.id)" />
                                <Button icon="pi pi-trash" text severity="danger" size="small" @click="markFileForDeletion(file.id)" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upload new files -->
                <div
                    class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center cursor-pointer hover:border-green-400 transition-colors"
                    @drop.prevent="handleBoqFilesDrop"
                    @dragover.prevent
                    @click="$refs.boqFilesInput.click()"
                >
                    <i class="pi pi-cloud-upload text-4xl text-green-400 mb-2"></i>
                    <p class="text-gray-600 mb-1">{{ existingBoqFiles.length > 0 ? 'Thêm file đề nghị/BOQ mới' : 'Kéo thả file đề nghị/BOQ vào đây hoặc click để chọn' }}</p>
                    <p class="text-sm text-gray-500">Hỗ trợ: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG</p>
                    <input
                        type="file"
                        ref="boqFilesInput"
                        @change="handleBoqFilesSelect"
                        multiple
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                        class="hidden"
                    />
                </div>
                <div v-if="uploadedBoqFiles.length > 0" class="mt-4">
                    <h4 class="font-semibold mb-2">File mới đã chọn:</h4>
                    <div class="space-y-2">
                        <div v-for="(file, index) in uploadedBoqFiles" :key="index" class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                            <div class="flex items-center space-x-3">
                                <i :class="getFileIcon(file.type)" class="text-xl"></i>
                                <div>
                                    <p class="font-medium text-sm">{{ file.name }}</p>
                                    <p class="text-xs text-gray-500">{{ formatFileSize(file.size) }}</p>
                                </div>
                            </div>
                            <Button icon="pi pi-times" text severity="danger" size="small" @click="removeBoqFile(index)" />
                        </div>
                    </div>
                </div>
                <small v-if="hasError('boq_files')" class="p-error block mt-1">{{ getError('boq_files') }}</small>
            </div>

            <!-- Action buttons -->
            <div class="flex justify-end gap-3 pt-4 border-t">
                <Button label="Hủy" icon="pi pi-times" severity="secondary" variant="outlined" @click="goBack" />
                <Button label="Cập nhật" icon="pi pi-save" type="submit" :loading="updating" />
            </div>
        </form>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useFormValidation } from '@/composables/useFormValidation';
import { useToast } from 'primevue/usetoast';

import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Image from 'primevue/image';

const toast = useToast();

// Define props
const props = defineProps({
    report: {
        type: Object,
        required: true
    },
    workflows: {
        type: Object,
        required: true
    },
    purchasingAdmins: {
        type: Array,
        required: true
    }
});

// Composables
const { errors, hasError, getError } = useFormValidation();

// Unwrap report data
const reportData = computed(() => props.report?.data || props.report);

// Separate existing files by type
const existingReportImage = computed(() => {
    const files = reportData.value.files || [];
    return files.find(f => f.type === 'REPORT_IMAGE') || null;
});

const existingQuotationFiles = computed(() => {
    const files = reportData.value.files || [];
    return files.filter(f => f.type === 'QUOTATION');
});

const existingBoqFiles = computed(() => {
    const files = reportData.value.files || [];
    return files.filter(f => f.type === 'BOQ');
});

// Filtered files (excluding deleted ones)
const existingQuotationFilesFiltered = computed(() => {
    return existingQuotationFiles.value.filter(f => !form.delete_files.includes(f.id));
});

const existingBoqFilesFiltered = computed(() => {
    return existingBoqFiles.value.filter(f => !form.delete_files.includes(f.id));
});

// Form data using Inertia's useForm
const form = useForm({
    title: reportData.value.title || '',
    workflow_type: reportData.value.workflow_type || null,
    purchasing_admin_id: (reportData.value.purchasing_admin?.data?.id || reportData.value.purchasing_admin?.id || reportData.value.purchasing_admin_id || null),
    report_image: null,
    quotation_files: [],
    boq_files: [],
    delete_files: [] // Track files to delete
});

const submitted = ref(false);
const updating = ref(false);

// Workflow type options from backend
const workflowTypeOptions = Object.entries(props.workflows).map(([value, label]) => ({
    label,
    value
}));

// Workflow descriptions for display
const workflowDescriptions = {
    'NORMAL': 'Trưởng phòng → Kiểm soát nội bộ → BGĐ',
    'SPECIAL_1': 'Trưởng phòng → Kiểm soát nội bộ → BGĐ 1 → BGĐ 2',
    'SPECIAL_2': 'Trưởng phòng → Khối Mua Hàng → Kiểm soát nội bộ → BGĐ',
    'SPECIAL_3': 'Trưởng phòng → Ban Kỹ thuật → Kiểm soát nội bộ → BGĐ',
    'URGENT': 'Trưởng phòng → BGĐ (bỏ qua Kiểm soát nội bộ)'
};

// Image paste/drop handling
const pasteAreaRef = ref(null);
const imagePreviewSrc = ref(null);
const imageFile = ref(null);
const showPlaceholder = ref(true);
const isContentEditable = ref(true);
const editorKey = ref(0);

function handleFocus() {
    showPlaceholder.value = false;
    isContentEditable.value = true;
}

function handleBlur() {
    if (!imagePreviewSrc.value && (!pasteAreaRef.value || pasteAreaRef.value.innerText.trim() === '')) {
        showPlaceholder.value = true;
    }
}

function handlePaste(e) {
    e.preventDefault();
    showPlaceholder.value = false;
    const items = (e.clipboardData || e.originalEvent?.clipboardData)?.items || [];
    for (const item of items) {
        if (item.type.indexOf('image') !== -1) {
            const file = item.getAsFile();
            imageFile.value = file;
            const reader = new FileReader();
            reader.onload = (ev) => {
                imagePreviewSrc.value = ev.target.result;
                form.report_image = ev.target.result;
            };
            reader.readAsDataURL(file);
            return;
        }
    }
}

function handleDrop(e) {
    const file = e.dataTransfer.files?.[0];
    if (!file || !file.type.startsWith('image/')) {
        toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Chỉ chấp nhận file ảnh.', life: 2500 });
        return;
    }
    imageFile.value = file;
    const reader = new FileReader();
    reader.onload = (ev) => {
        imagePreviewSrc.value = ev.target.result;
        form.report_image = ev.target.result;
    };
    reader.readAsDataURL(file);
}

function removeImage() {
    form.report_image = null;
    imagePreviewSrc.value = null;
    imageFile.value = null;
    showPlaceholder.value = true;
    isContentEditable.value = true;
    editorKey.value++;
}

// Quotation files handling
const uploadedQuotationFiles = ref([]);
const quotationFilesInput = ref(null);

function handleQuotationFilesDrop(e) {
    addQuotationFiles(Array.from(e.dataTransfer.files || []));
}

function handleQuotationFilesSelect(e) {
    addQuotationFiles(Array.from(e.target.files || []));
}

function addQuotationFiles(files) {
    const allowedTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg', 'image/jpg', 'image/png'
    ];
    const allowedExtensions = ['.pdf', '.doc', '.docx', '.xls', '.xlsx', '.jpg', '.jpeg', '.png'];
    const valid = [];
    const invalid = [];

    files.forEach(f => {
        let ok = false;
        const ext = f.name && f.name.lastIndexOf('.') !== -1 ? f.name.slice(f.name.lastIndexOf('.')).toLowerCase() : '';
        if (allowedTypes.includes(f.type)) {
            ok = true;
        } else if (
            (f.type === 'application/octet-stream' || f.type === '' || f.type.startsWith('application/'))
            && allowedExtensions.includes(ext)
        ) {
            ok = true;
        }
        if (ok) {
            valid.push(f);
        } else {
            invalid.push(f);
        }
    });

    if (invalid.length > 0) {
        toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Một số file không được hỗ trợ và đã bị bỏ qua.', life: 2500 });
    }

    uploadedQuotationFiles.value.push(...valid);
    form.quotation_files = [...uploadedQuotationFiles.value];
}

function removeQuotationFile(index) {
    uploadedQuotationFiles.value.splice(index, 1);
    form.quotation_files = [...uploadedQuotationFiles.value];
}

// BOQ files handling
const uploadedBoqFiles = ref([]);
const boqFilesInput = ref(null);

function handleBoqFilesDrop(e) {
    addBoqFiles(Array.from(e.dataTransfer.files || []));
}

function handleBoqFilesSelect(e) {
    addBoqFiles(Array.from(e.target.files || []));
}

function addBoqFiles(files) {
    const allowedTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg', 'image/jpg', 'image/png'
    ];
    const allowedExtensions = ['.pdf', '.doc', '.docx', '.xls', '.xlsx', '.jpg', '.jpeg', '.png'];
    const valid = [];
    const invalid = [];

    files.forEach(f => {
        let ok = false;
        const ext = f.name && f.name.lastIndexOf('.') !== -1 ? f.name.slice(f.name.lastIndexOf('.')).toLowerCase() : '';
        if (allowedTypes.includes(f.type)) {
            ok = true;
        } else if (
            (f.type === 'application/octet-stream' || f.type === '' || f.type.startsWith('application/'))
            && allowedExtensions.includes(ext)
        ) {
            ok = true;
        }
        if (ok) {
            valid.push(f);
        } else {
            invalid.push(f);
        }
    });

    if (invalid.length > 0) {
        toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Một số file không được hỗ trợ và đã bị bỏ qua.', life: 2500 });
    }

    uploadedBoqFiles.value.push(...valid);
    form.boq_files = [...uploadedBoqFiles.value];
}

function removeBoqFile(index) {
    uploadedBoqFiles.value.splice(index, 1);
    form.boq_files = [...uploadedBoqFiles.value];
}

// Helper functions
function getFileIcon(mime) {
    const map = {
        'application/pdf': 'pi pi-file-pdf text-red-500',
        'application/msword': 'pi pi-file-word text-blue-500',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'pi pi-file-word text-blue-500',
        'application/vnd.ms-excel': 'pi pi-file-excel text-green-500',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'pi pi-file-excel text-green-500',
        'image/jpeg': 'pi pi-image text-purple-500',
        'image/jpg': 'pi pi-image text-purple-500',
        'image/png': 'pi pi-image text-purple-500',
    };
    return map[mime] || 'pi pi-file';
}

function getFileIconByName(filename) {
    const ext = filename.split('.').pop().toLowerCase();
    const extMap = {
        'pdf': 'pi pi-file-pdf text-red-500',
        'doc': 'pi pi-file-word text-blue-500',
        'docx': 'pi pi-file-word text-blue-500',
        'xls': 'pi pi-file-excel text-green-500',
        'xlsx': 'pi pi-file-excel text-green-500',
        'jpg': 'pi pi-image text-purple-500',
        'jpeg': 'pi pi-image text-purple-500',
        'png': 'pi pi-image text-purple-500',
    };
    return extMap[ext] || 'pi pi-file';
}

function formatFileSize(bytes) {
    if (!bytes) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(2))} ${sizes[i]}`;
}

function dataURLtoBlob(dataurl) {
    const arr = dataurl.split(',');
    const mime = arr[0].match(/:(.*?);/)[1];
    const bstr = atob(arr[1]);
    const len = bstr.length;
    const u8 = new Uint8Array(len);
    for (let i = 0; i < len; i++) {
        u8[i] = bstr.charCodeAt(i);
    }
    return new Blob([u8], { type: mime });
}

function viewFile(fileId) {
    window.open(`/vendor-reports/files/${fileId}/view`, '_blank');
}

function downloadFile(fileId) {
    window.location.href = `/vendor-reports/files/${fileId}/download`;
}

function markFileForDeletion(fileId) {
    if (!form.delete_files.includes(fileId)) {
        form.delete_files.push(fileId);
    }
}

function isFileMarkedForDeletion(fileId) {
    return form.delete_files.includes(fileId);
}

const getWorkflowDescription = (workflowType) => {
    return workflowDescriptions[workflowType] || '';
};

const goBack = () => {
    router.get(`/vendor-reports/${reportData.value.id}`);
};

const updateReport = () => {
    submitted.value = true;

    // Basic client-side validation
    if (!form.title || !form.workflow_type) {
        return;
    }

    updating.value = true;

    // Check if we need multipart form data
    const hasNewImage = typeof form.report_image === 'string' && form.report_image.startsWith('data:image');
    const hasQuotationFiles = form.quotation_files.length > 0;
    const hasBoqFiles = form.boq_files.length > 0;
    const hasDeleteFiles = form.delete_files.length > 0;
    const needsMultipart = hasNewImage || hasQuotationFiles || hasBoqFiles || hasDeleteFiles;

    if (needsMultipart) {
        form
            .transform((data) => {
                const formData = {
                    title: data.title,
                    workflow_type: data.workflow_type,
                    purchasing_admin_id: data.purchasing_admin_id || null,
                    quotation_files: Array.isArray(data.quotation_files) ? data.quotation_files : [],
                    boq_files: Array.isArray(data.boq_files) ? data.boq_files : [],
                    delete_files: Array.isArray(data.delete_files) ? data.delete_files : [],
                    _method: 'PUT'
                };

                if (typeof data.report_image === 'string' && data.report_image.startsWith('data:image')) {
                    formData.report_image = dataURLtoBlob(data.report_image);
                } else if (data.report_image instanceof File) {
                    formData.report_image = data.report_image;
                }

                return formData;
            })
            .post(`/vendor-reports/${reportData.value.id}`, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    updating.value = false;
                },
                onError: (errors) => {
                    updating.value = false;
                    submitted.value = true;
                },
            });
    } else {
        form.put(`/vendor-reports/${reportData.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                updating.value = false;
            },
            onError: () => {
                updating.value = false;
                submitted.value = true;
            },
        });
    }
};
</script>

<style scoped>
.paste-content-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 140px;
    width: 100%;
    text-align: center;
    color: var(--text-color-secondary);
    font-style: italic;
    background: var(--surface-100);
    border-radius: var(--border-radius);
    padding: 1rem;
    box-sizing: border-box;
}

.pasted-image-preview {
    max-width: 100%;
    max-height: 100%;
    display: block;
    margin: auto;
    object-fit: contain;
}

.p-editor-container {
    border: 1px solid var(--surface-300);
    border-radius: var(--border-radius);
    padding: 1rem;
    cursor: text;
    min-height: 150px;
    box-sizing: border-box;
    overflow: hidden;
    display: flex;
    justify-content: center;
    align-items: center;
}

.p-editor-container.has-content {
    background-color: var(--surface-0);
}

.placeholder-text {
    margin: 0;
}
</style>
