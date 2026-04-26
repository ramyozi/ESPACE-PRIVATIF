import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'node:path'

// On proxifie /api et /health vers le backend Slim afin de partager la
// meme origine (cookies de session) et eviter la configuration CORS.
export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  server: {
    port: 5173,
    proxy: {
      '/api': 'http://localhost:8080',
      '/health': 'http://localhost:8080',
    },
  },
})
