<template>
    <Head>
        <title>403 - Không có quyền truy cập</title>
    </Head>

    <div>
        <div class="card">
            <div class="error-content">
            <!-- Error Icon -->
            <div class="mb-8">
                <i class="pi pi-lock error-icon"></i>
            </div>

            <!-- Error Code -->
            <h1 class="error-code">403</h1>

            <!-- Error Message -->
            <h2 class="error-title">
                Không có quyền truy cập
            </h2>
            <p class="error-description">
                Bạn không có quyền truy cập vào trang này. Vui lòng liên hệ quản trị viên nếu bạn cho rằng đây là lỗi.
            </p>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center mb-8">
                <Button
                    label="Quay lại trang chủ"
                    icon="pi pi-home"
                    @click="goHome"
                    size="large"
                />
                <Button
                    label="Quay lại trang trước"
                    icon="pi pi-arrow-left"
                    @click="goBack"
                    severity="secondary"
                    outlined
                    size="large"
                />
            </div>

            <!-- Additional Info -->
            <div class="info-card">
                <h3 class="info-title">
                    <i class="pi pi-info-circle mr-2"></i>
                    Tại sao tôi thấy trang này?
                </h3>
                <ul class="info-list">
                    <li class="info-item">
                        <i class="pi pi-check-circle mr-2"></i>
                        <span>Bạn không có vai trò phù hợp để truy cập trang này</span>
                    </li>
                    <li class="info-item">
                        <i class="pi pi-check-circle mr-2"></i>
                        <span>Bạn không có quyền thực hiện thao tác này</span>
                    </li>
                    <li class="info-item">
                        <i class="pi pi-check-circle mr-2"></i>
                        <span>Tài khoản của bạn chưa được cấp quyền cần thiết</span>
                    </li>
                </ul>
            </div>

            <!-- Contact Support -->
            <div class="contact-section">
                <p class="mb-2">Cần hỗ trợ?</p>
                <p class="text-sm">
                    <i class="pi pi-envelope mr-1"></i>
                    Liên hệ: <a href="mailto:admin@example.com" class="contact-link">admin@example.com</a>
                </p>
            </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import Button from 'primevue/button';

const goHome = () => {
    router.visit('/');
};

const goBack = () => {
    window.history.back();
};
</script>

<style scoped>
.error-content {
    text-align: center;
    max-width: 800px;
    width: 100%;
    margin: 0 auto;
    padding: 2rem 0;
}

.error-icon {
    font-size: 6rem;
    color: var(--red-500);
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

.error-code {
    font-size: 8rem;
    font-weight: 800;
    color: var(--red-600);
    margin-bottom: 1rem;
    line-height: 1;
}

.error-title {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-color);
    margin-bottom: 1rem;
}

.error-description {
    font-size: 1.25rem;
    color: var(--text-color-secondary);
    margin-bottom: 2rem;
    max-width: 28rem;
    margin-left: auto;
    margin-right: auto;
}

.info-card {
    margin-top: 3rem;
    padding: 2rem;
    background: var(--surface-card);
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    max-width: 42rem;
    margin-left: auto;
    margin-right: auto;
}

.info-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-color);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.info-list {
    list-style: none;
    padding: 0;
    margin: 0;
    text-align: left;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.info-item {
    display: flex;
    align-items: flex-start;
    color: var(--text-color-secondary);
}

.info-item i {
    color: var(--green-500);
    margin-top: 0.25rem;
    flex-shrink: 0;
}

.contact-section {
    margin-top: 2rem;
    color: var(--text-color-secondary);
}

.contact-link {
    color: var(--primary-color);
    text-decoration: none;
}

.contact-link:hover {
    text-decoration: underline;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

/* Responsive */
@media (max-width: 640px) {
    .error-code {
        font-size: 5rem;
    }

    .error-title {
        font-size: 1.5rem;
    }

    .error-description {
        font-size: 1rem;
    }

    .info-card {
        padding: 1.5rem;
    }
}
</style>
