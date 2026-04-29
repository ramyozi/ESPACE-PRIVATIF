/// <reference types="vite/client" />

// Variables d'environnement Vite typees, utilisees a la build du frontend.
interface ImportMetaEnv {
  readonly VITE_API_BASE_URL?: string
  readonly VITE_WS_URL?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
