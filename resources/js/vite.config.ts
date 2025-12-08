import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueJsx from '@vitejs/plugin-vue-jsx'
import VueDevTools from 'vite-plugin-vue-devtools'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    vueJsx(),
    VueDevTools(),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    },
  },
  build: {
    outDir: '../../public/assets',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        main: fileURLToPath(new URL('./src/main.ts', import.meta.url))
      }
    }
  },
  server: {
    port: 5173,
    strictPort: true,
    cors: true
  }
})
