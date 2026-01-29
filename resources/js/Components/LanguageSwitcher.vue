<script setup>
import { ref, computed } from 'vue';
import { useI18n } from '../composables/useI18n';

const { locale, setLocale, availableLocales, t } = useI18n();
const showLanguageMenu = ref(false);

const switchLanguage = (localeCode) => {
  console.log('Switching from', locale.value, 'to', localeCode);
  setLocale(localeCode);
  showLanguageMenu.value = false;

  // Log after switch
  setTimeout(() => {
    console.log('After switch, locale.value:', locale.value);
  }, 100);
};

const getCurrentLanguage = () => {
  return availableLocales.find(lang => lang.code === locale.value);
};

// Use computed to ensure reactivity
const currentFlag = computed(() => {
  console.log('Computing currentFlag, locale.value:', locale.value);
  const current = availableLocales.find(lang => lang.code === locale.value);
  return current?.flag || '🌐';
});
</script>

<template>
  <div class="relative">
    <button
      v-styleclass="{
        selector: '@next',
        enterFromClass: 'hidden',
        enterActiveClass: 'animate-scalein',
        leaveToClass: 'hidden',
        leaveActiveClass: 'animate-fadeout',
        hideOnOutsideClick: true
      }"
      type="button"
      class="flex items-center justify-center w-10 h-10 rounded-full hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors text-surface-700 dark:text-surface-300"
    >
      <span class="text-xl">{{ currentFlag }}</span>
    </button>

    <div class="absolute right-0 top-16 bg-surface-0 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 rounded-lg shadow-lg p-2 min-w-48 z-50 hidden">
      <div class="mb-2 px-3 py-2 text-sm font-medium text-surface-600 dark:text-surface-400 border-b border-surface-200 dark:border-surface-700">
        {{ t('common.language') }}
      </div>
      <div
        v-for="lang in availableLocales"
        :key="lang.code"
        @click="switchLanguage(lang.code)"
        class="flex items-center gap-3 px-3 py-2 rounded cursor-pointer hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
        :class="{ 'bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400': locale === lang.code }"
      >
        <span class="text-lg">{{ lang.flag }}</span>
        <span class="font-medium">{{ lang.name }}</span>
        <i v-if="locale === lang.code" class="pi pi-check ml-auto text-primary-600 dark:text-primary-400"></i>
      </div>
    </div>
  </div>
</template>

<style scoped>
.animate-scalein {
  animation: scalein 0.15s linear;
}

.animate-fadeout {
  animation: fadeout 0.15s linear;
}

@keyframes scalein {
  0% {
    opacity: 0;
    transform: scaleX(0.8) scaleY(0.8);
  }
  100% {
    opacity: 1;
    transform: scaleX(1) scaleY(1);
  }
}

@keyframes fadeout {
  0% {
    opacity: 1;
  }
  100% {
    opacity: 0;
  }
}
</style>
