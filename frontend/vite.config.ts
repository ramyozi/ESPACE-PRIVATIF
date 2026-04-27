import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'node:path'

// Configuration adaptee a un environnement Docker.
//
// On proxifie /api et /health vers le service nginx du compose (nomme "web")
// qui sert l'application Slim sur le port 80. C'est volontairement le nom de
// service Docker (et non localhost) afin que le conteneur frontend joigne
// le backend via le reseau interne docker-compose.
//
// host: true permet a Vite d'ecouter sur 0.0.0.0 (sans cela, le conteneur
// n'est pas joignable depuis l'hote a http://localhost:5173).
export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  server: {
    host: true,
    port: 5173,
    proxy: {
      '/api': 'http://web:80',
      '/health': 'http://web:80',
    },
    // En conteneur, le file watcher natif n'est pas toujours fiable :
    // on force le polling via la variable d'env CHOKIDAR_USEPOLLING.
    watch: {
      usePolling: true,
    },
  },
})
