// vite.config.ts
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import laravel from 'laravel-vite-plugin'
import tailwindcss from '@tailwindcss/vite'
import { wayfinder } from '@laravel/vite-plugin-wayfinder'

import Components from 'unplugin-vue-components/vite'
import { PrimeVueResolver } from '@primevue/auto-import-resolver'

export default defineConfig({
  plugins: [
    // 1) Vue ก่อน (ให้ Vite แปลง SFC ก่อน)
    vue({
      template: {
        transformAssetUrls: { base: null, includeAbsolute: false },
      },
    }),

    // 2) Auto-import PrimeVue components
    Components({
      resolvers: [PrimeVueResolver()],
      dts: false, // ไม่ต้อง gen .d.ts
    }),

    // 3) Laravel Vite (entry + SSR ของ Inertia)
    laravel({
      input: ['resources/js/app.ts'],
      ssr: 'resources/js/ssr.ts', // ถ้าไม่มีไฟล์นี้ ให้ลบบรรทัดนี้ออก
      refresh: true,
    }),

    // 4) Tailwind v4 plugin
    tailwindcss(),

    // 5) Wayfinder (ถ้าใช้ฟอร์มของ Breeze/Jetstream)
    wayfinder({
      formVariants: true,
    }),
  ],
})
