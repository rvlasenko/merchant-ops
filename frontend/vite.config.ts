import react from '@vitejs/plugin-react'
import { defineConfig } from 'vitest/config'

// The browser talks only to this dev server; /api is forwarded to Laravel
// inside the Docker network, so the backend needs no CORS (spec 2.1, D4).
const apiProxyTarget = process.env.API_PROXY_TARGET ?? 'http://backend:8000'

export default defineConfig({
  plugins: [react()],
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    proxy: {
      '/api': { target: apiProxyTarget, changeOrigin: true },
    },
  },
  test: {
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
  },
})
