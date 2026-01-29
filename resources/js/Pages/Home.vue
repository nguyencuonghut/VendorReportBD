<template>
    <Head>
        <title>{{ t('home.title') }}</title>
    </Head>

    <div class="min-h-screen bg-gray-100 py-6 flex flex-col justify-center sm:py-12">
        <div class="relative py-3 sm:max-w-xl sm:mx-auto">
            <div class="absolute inset-0 bg-gradient-to-r from-cyan-400 to-light-blue-500 shadow-lg transform -skew-y-6 sm:skew-y-0 sm:-rotate-6 sm:rounded-3xl"></div>
            <div class="relative px-4 py-10 bg-white shadow-lg sm:rounded-3xl sm:p-20">
                <div class="max-w-md mx-auto">
                    <div class="flex items-center space-x-5">
                        <div class="h-14 w-14 bg-yellow-200 rounded-full flex flex-shrink-0 justify-center items-center text-yellow-500 text-2xl font-mono">
                            🏠
                        </div>
                        <div class="block pl-2 font-semibold text-xl self-start text-gray-700">
                            <h2 class="leading-relaxed">{{ t('home.title') }}</h2>
                            <p class="text-sm text-gray-500 font-normal leading-relaxed">{{ t('home.subtitle') }}</p>
                        </div>
                    </div>
                    <div class="divide-y divide-gray-200">
                        <div class="py-8 text-base leading-6 space-y-4 text-gray-700 sm:text-lg sm:leading-7">
                            <p>{{ t('home.welcomeMessage') }}</p>
                            <ul class="list-disc space-y-2">
                                <li class="flex items-start">
                                    <span class="h-6 flex items-center sm:h-7">
                                        <svg class="flex-shrink-0 h-5 w-5 text-cyan-500" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                        </svg>
                                    </span>
                                    <p class="ml-2">{{ t('home.features.studentManagement') }}</p>
                                </li>
                                <li class="flex items-start">
                                    <span class="h-6 flex items-center sm:h-7">
                                        <svg class="flex-shrink-0 h-5 w-5 text-cyan-500" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                        </svg>
                                    </span>
                                    <p class="ml-2">{{ t('home.features.courseTracking') }}</p>
                                </li>
                                <li class="flex items-start">
                                    <span class="h-6 flex items-center sm:h-7">
                                        <svg class="flex-shrink-0 h-5 w-5 text-cyan-500" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                        </svg>
                                    </span>
                                    <p class="ml-2">{{ t('home.features.progressMonitoring') }}</p>
                                </li>
                            </ul>
                        </div>
                        <div class="pt-6 text-base leading-6 font-bold sm:text-lg sm:leading-7">
                            <Button
                                @click="handleLogout"
                                :label="t('auth.logout')"
                                severity="secondary"
                                outlined
                                class="mr-2"
                            />
                            <Button
                                :label="t('home.startUsing')"
                                severity="primary"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { AuthService } from '../services';
import { useFlashMessages } from '../composables/useFlashMessages';
import { useI18n } from '../composables/useI18n';
import { onMounted } from 'vue';

const { handleFlashMessages } = useFlashMessages();
const { t } = useI18n();

// Handle flash messages when component mounts
onMounted(() => {
    handleFlashMessages();
});

// Handle logout
const handleLogout = () => {
    AuthService.logout();
};
</script>
