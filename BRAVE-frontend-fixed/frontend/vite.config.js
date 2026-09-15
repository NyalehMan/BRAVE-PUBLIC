import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

export default defineConfig(({ command }) => ({
  base: '/',

  plugins: [vue(), command === 'serve' && vueDevTools()].filter(Boolean),

  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },

  optimizeDeps: {
    exclude: ['@arcgis/core'],
  },

  build: {
    outDir: '../backend/public',
    emptyOutDir: false,
    target: 'es2022',
    minify: 'esbuild',
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (id.includes('/node_modules/@arcgis/core/')) {
            return 'arcgis'
          }

          if (
            id.includes('/node_modules/apexcharts/') ||
            id.includes('/node_modules/chart.js/') ||
            id.includes('/node_modules/vue-chartjs/') ||
            id.includes('/node_modules/vue3-apexcharts/')
          ) {
            return 'charts'
          }

          if (
            id.includes('/node_modules/vue/') ||
            id.includes('/node_modules/vue-router/') ||
            id.includes('/node_modules/pinia/')
          ) {
            return 'vue-vendor'
          }

          return undefined
        },
      },
    },
  },
}))
