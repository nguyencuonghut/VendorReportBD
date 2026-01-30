<template>
    <Head>
        <title>Chỉnh sửa phiếu: {{ props.report.title }}</title>
    </Head>

    <div class="card">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold">Chỉnh sửa phiếu</h2>
            <Tag v-if="props.report.status_label" :value="props.report.status_label" :severity="props.report.status_color" />
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

            <!-- Cán bộ mua hàng theo dõi -->
            <div>
                <label for="purchasing_admin_id" class="block font-bold mb-3">Cán bộ mua hàng theo dõi</label>
                <Select
                    id="purchasing_admin_id"
                    v-model="form.purchasing_admin_id"
                    :options="props.purchasingAdmins"
                    optionLabel="name"
                    optionValue="id"
                    placeholder="Chọn cán bộ mua hàng"
                    :invalid="hasError('purchasing_admin_id')"
                    fluid
                    filter
                    showClear
                />
                <small class="text-gray-600 block mt-1">Chỉ để theo dõi, không tham gia vào quy trình phê duyệt</small>
                <small v-if="hasError('purchasing_admin_id')" class="p-error block mt-1">{{ getError('purchasing_admin_id') }}</small>
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
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { VendorReportService } from '@/services';
import { useFormValidation } from '@/composables/useFormValidation';

import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import Button from 'primevue/button';
import Tag from 'primevue/tag';

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

// Reactive data
const form = ref({
    title: props.report.title || '',
    workflow_type: props.report.workflow_type || null,
    purchasing_admin_id: props.report.purchasing_admin_id || null
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

// Helper functions
const getWorkflowDescription = (workflowType) => {
    return workflowDescriptions[workflowType] || '';
};;

const goBack = () => {
    router.get(`/vendor-reports/${props.report.id}`);
};

const updateReport = () => {
    submitted.value = true;

    // Basic client-side validation
    if (!form.value.title || !form.value.workflow_type) {
        return;
    }

    updating.value = true;

    const reportData = {
        title: form.value.title,
        workflow_type: form.value.workflow_type,
        purchasing_admin_id: form.value.purchasing_admin_id || null,
        content: form.value.content || null,
        notes: form.value.notes || null
    };

    VendorReportService.update(props.report.id, reportData, {
        onSuccess: () => {
            updating.value = false;
            // Backend will redirect to show page
        },
        onError: () => {
            updating.value = false;
        }
    });
};
</script>

<style scoped>
</style>
