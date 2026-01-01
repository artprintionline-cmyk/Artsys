import { defineConfig } from 'vite'

// Build config: output to Laravel public folder for single-server setup
export default defineConfig({
  base: '/',
  build: {
    outDir: '../erp-api/public',
    emptyOutDir: false,
  },
  server: {
    port: 5174,
    hmr: {
      host: 'localhost',
      port: 5174,
    },
  },
})
