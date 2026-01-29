import './bootstrap';

import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'

import PrimeVue from 'primevue/config';
import Aura from '@primeuix/themes/aura';

import AppLayout from './SakaiVue/layout/AppLayout.vue';
import ConfirmationService from 'primevue/confirmationservice';
import ToastService from 'primevue/toastservice';
import StyleClass from 'primevue/styleclass';
import Tooltip from 'primevue/tooltip';
import i18nPlugin from './plugins/i18n';

import Toast from 'primevue/toast';

import './SakaiVue/assets/styles.scss';

createInertiaApp({
  resolve: name => {
    const pages = import.meta.glob('./Pages/**/*.vue', { eager: true })
    let page = pages[`./Pages/${name}.vue`]
    if (page.default.layout === undefined) {
      page.default.layout = AppLayout
    }

    return page
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(i18nPlugin)
      .use(PrimeVue, {
            theme: {
                preset: Aura,
                options: {
                    darkModeSelector: '.app-dark',
                    cssLayer: false
                }
            }
        })
      .use(ConfirmationService)
      .use(ToastService)
      .directive('styleclass', StyleClass)
      .directive('tooltip', Tooltip)
      .component('Toast', Toast)
      .mount(el)
  },
})
