<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import Button from 'primevue/button';

const isLoading = ref(false);

const performBackup = () => {
    if (isLoading.value) return;

    isLoading.value = true;

    // Sử dụng window.location để navigate đến route download
    // Đây là cách đơn giản nhất cho file download
    window.location.href = '/backup/download';

    // Reset loading state sau 3 giây
    setTimeout(() => {
        isLoading.value = false;
    }, 5000);
};
</script>

<template>
    <Head title="Backup Hệ Thống" />

    <div class="grid">
        <div class="col-12">
            <div class="card">
                <h5 class="mb-4">Backup Hệ Thống</h5>

                <div class="p-fluid">
                    <div class="field">
                        <label class="block text-900 font-medium mb-2">Thông tin Backup</label>
                        <div class="text-600 line-height-3 mb-4">
                            Chức năng này sẽ tạo bản sao lưu toàn bộ hệ thống bao gồm:
                        </div>
                        <ul class="text-600 line-height-3 mb-4 ml-3">
                            <li>• Cơ sở dữ liệu (MySQL dump)</li>
                            <li>• File cấu hình (.env)</li>
                            <li>• Thư mục tệp tin tải lên</li>
                        </ul>
                        <div class="text-600 line-height-3 mb-4">
                            Tất cả sẽ được nén thành file ZIP và tự động tải về máy tính của bạn.
                        </div>
                    </div>

                    <div class="field">
                        <label class="block text-900 font-medium mb-2">Lưu ý quan trọng</label>
                        <div class="p-3 surface-100 border-round text-600 line-height-3 mb-4">
                            <i class="pi pi-exclamation-triangle text-orange-500 mr-2"></i>
                            <strong>Cảnh báo:</strong> Quá trình backup có thể mất vài phút tùy thuộc vào dung lượng dữ liệu.
                            Vui lòng không đóng trang web trong quá trình thực hiện.
                        </div>
                    </div>

                    <div class="flex justify-content-center">
                        <Button
                            @click="performBackup"
                            :loading="isLoading"
                            :disabled="isLoading"
                            class="p-button-lg"
                            icon="pi pi-cloud-download"
                            :label="isLoading ? 'Đang backup...' : 'Thực hiện Backup'"
                            severity="success"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.card {
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.p-button-lg {
    font-size: 1.2rem;
    padding: 0.875rem 2rem;
}
</style>
