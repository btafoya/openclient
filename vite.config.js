import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [vue()],

  resolve: {
    alias: {
      '@': resolve(__dirname, './resources/js'),
    },
  },

  build: {
    // Output directory (relative to project root)
    outDir: 'public/assets',

    // Empty output directory before build
    emptyOutDir: true,

    // Generate manifest for asset mapping
    manifest: true,

    rollupOptions: {
      input: {
        // Main application entry point
        app: resolve(__dirname, 'resources/js/app.js'),

        // Separate CSS entry
        styles: resolve(__dirname, 'resources/css/app.css'),
      },

      output: {
        // Asset file naming
        assetFileNames: (assetInfo) => {
          const info = assetInfo.name.split('.')
          let extType = info[info.length - 1]

          if (/png|jpe?g|svg|gif|tiff|bmp|ico/i.test(extType)) {
            return `images/[name]-[hash][extname]`
          }

          if (/woff|woff2|eot|ttf|otf/i.test(extType)) {
            return `fonts/[name]-[hash][extname]`
          }

          return `[ext]/[name]-[hash][extname]`
        },

        // Chunk file naming
        chunkFileNames: 'js/[name]-[hash].js',

        // Entry file naming
        entryFileNames: 'js/[name]-[hash].js',
      },
    },
  },

  server: {
    // Development server port
    port: 5173,

    // Serve files from public directory
    hmr: {
      host: 'localhost',
    },
  },
})
