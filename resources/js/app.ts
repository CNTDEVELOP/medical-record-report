// resources/js/app.ts
import '../css/app.css'

import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import type { DefineComponent } from 'vue'
import { createApp, h } from 'vue'
import PrimeVue from 'primevue/config'
import { DentalBlue } from './primevue-theme'
import 'primeicons/primeicons.css'

import ToastService from 'primevue/toastservice'
import ConfirmationService from 'primevue/confirmationservice'

// ⬇️ เพิ่มบรรทัดนี้
import AppLayout from '@/Layouts/AppLayout.vue'

const appName = import.meta.env.VITE_APP_NAME || 'Laravel'

createInertiaApp({
  title: (title) => (title ? `${title} - ${appName}` : appName),
  resolve: (name) =>
    resolvePageComponent(
      `./pages/${name}.vue`,
      import.meta.glob<DefineComponent>('./pages/**/*.vue')
    ).then((mod: any) => {
      // ✅ ตั้งค่า Default Layout เฉพาะกรณี "ยังไม่กำหนด"
      // (ถ้าหน้าไหนกำหนด layout เองแล้ว หรือกำหนดเป็น null จะไม่ทับ)
      if (mod?.default && mod.default.layout === undefined) {
        mod.default.layout = AppLayout
      }
      return mod
    }),
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(PrimeVue, {
        ripple: true,
        inputVariant: 'filled',
        theme: {
          preset: DentalBlue,
          options: { darkModeSelector: '.app-dark' },
        },
        zIndex: { modal: 1200, overlay: 1100, menu: 1000, tooltip: 1300 },
      })
      .use(ToastService)
      .use(ConfirmationService)
      .mount(el)
  },
  progress: { color: '#4B5563' },
})
