import { useI18n } from '../composables/useI18n';

export default {
  install(app) {
    const i18n = useI18n();

    // Provide globally
    app.provide('i18n', i18n);

    // Add as global property
    app.config.globalProperties.$t = i18n.t;
    app.config.globalProperties.$locale = i18n.locale;
    app.config.globalProperties.$setLocale = i18n.setLocale;
  }
};
