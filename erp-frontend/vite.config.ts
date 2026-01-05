import { defineConfig } from 'vite'

// Build config: output to Laravel public folder for single-server setup
export default defineConfig({
  base: '/',
  build: {
    outDir: '../erp-api/public',
    emptyOutDir: false,
  },
  server: {
    port: 5173,
    hmr: {
      host: 'localhost',
      port: 5173,
    },
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
    },
  },
})
