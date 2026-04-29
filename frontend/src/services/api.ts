/**
 * Wrapper fetch pour ESPACE-PRIVATIF.
 *
 * - centralise la gestion des erreurs API
 * - injecte le header X-CSRF-Token sur les requetes mutantes
 * - traduit les codes d'erreur backend en messages utilisateur en francais
 * - inclut systematiquement les cookies de session (credentials: 'include')
 */

// Base URL de l'API.
//  - en local : "/api" (proxifie par Vite vers le backend)
//  - en cloud : "https://<api>.onrender.com/api" via VITE_API_BASE_URL
//
// On retire le slash final si present pour eviter le "//api" en concat.
const RAW_BASE = (import.meta.env.VITE_API_BASE_URL ?? '').replace(/\/$/, '')
const API_BASE = `${RAW_BASE}/api`

/**
 * URL publique du serveur WebSocket Ratchet, exposee pour le client WS.
 *  - en local : "ws://localhost:8081" (defaut)
 *  - en cloud : VITE_WS_URL = wss://<service-ws>.onrender.com
 */
export const WS_URL: string =
  (import.meta.env.VITE_WS_URL as string | undefined) ?? 'ws://localhost:8081'

// Le token CSRF est conserve en memoire (pas en localStorage : il vit avec
// la session courante du navigateur, comme le cookie). On l'alimente apres
// le login ou via getCsrfToken().
let csrfToken: string | null = null

export function setCsrfToken(token: string | null) {
  csrfToken = token
}

export function getCsrfTokenInMemory(): string | null {
  return csrfToken
}

/**
 * Erreur metier remontee par l'API ou par le wrapper.
 * Le champ "code" suit la convention backend (auth_required, otp_invalid, etc.).
 */
export class ApiError extends Error {
  constructor(
    public readonly code: string,
    public readonly status: number,
    message: string,
  ) {
    super(message)
    this.name = 'ApiError'
  }
}

interface ApiOk<T> {
  status: 'ok'
  data: T
}

interface ApiErrPayload {
  status: 'error'
  error: { code: string; message: string }
}

type ApiResponse<T> = ApiOk<T> | ApiErrPayload

interface RequestOptions {
  method?: string
  body?: unknown
  // Pour les actions mutantes, le wrapper recupere le token CSRF si absent.
  withCsrf?: boolean
  signal?: AbortSignal
}

/**
 * Mappage des codes backend connus vers des messages utilisateur clairs.
 * Tout code non liste retombe sur un message generique.
 */
const errorMessages: Record<string, string> = {
  auth_required: 'Authentification requise',
  auth_invalid: 'Session invalide, merci de vous reconnecter',
  invalid_credentials: 'Identifiants incorrects',
  invalid_input: 'Informations manquantes ou invalides',
  document_not_found: 'Document introuvable',
  invalid_state: "Cette action n'est pas possible sur ce document",
  document_expired: 'Le document a expire',
  already_signed: 'Ce document a deja ete signe',
  otp_not_found: 'Aucun code en cours, relancez la signature',
  otp_invalid: 'Code OTP invalide',
  otp_locked: 'Trop de tentatives, demandez un nouveau code',
  invalid_image: "L'image de signature est invalide",
  csrf_invalid: 'Session expiree, rechargez la page',
}

function humanize(code: string, fallback: string): string {
  return errorMessages[code] ?? fallback
}

async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const method = (options.method ?? 'GET').toUpperCase()
  const isMutating = method !== 'GET' && method !== 'HEAD'

  const headers: Record<string, string> = {}
  if (options.body !== undefined) {
    headers['Content-Type'] = 'application/json'
  }

  if (isMutating && options.withCsrf !== false) {
    // Recupere un token CSRF si on n'en a pas encore
    if (!csrfToken) {
      try {
        csrfToken = await fetchCsrfToken()
      } catch {
        // On laisse le backend renvoyer 403, plus simple a gerer en aval
      }
    }
    if (csrfToken) {
      headers['X-CSRF-Token'] = csrfToken
    }
  }

  let response: Response
  try {
    response = await fetch(`${API_BASE}${path}`, {
      method,
      credentials: 'include',
      headers,
      body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
      signal: options.signal,
    })
  } catch (e) {
    // Echec reseau, navigateur offline, etc.
    throw new ApiError('network_error', 0, 'Impossible de contacter le serveur')
  }

  // Reponses sans contenu (204)
  if (response.status === 204) {
    return undefined as T
  }

  let json: ApiResponse<T>
  try {
    json = (await response.json()) as ApiResponse<T>
  } catch {
    throw new ApiError('invalid_response', response.status, 'Reponse serveur invalide')
  }

  if (json.status === 'error') {
    const code = json.error?.code ?? 'unknown_error'
    throw new ApiError(code, response.status, humanize(code, json.error?.message ?? 'Erreur inattendue'))
  }

  if (!response.ok) {
    throw new ApiError('http_error', response.status, 'Erreur serveur, reessayez plus tard')
  }

  return json.data
}

async function fetchCsrfToken(): Promise<string> {
  const data = await request<{ csrfToken: string }>('/auth/csrf-token', { withCsrf: false })
  return data.csrfToken
}

// ----------------------------------------------------------------
// Endpoints metier exposes au reste de l'application
// ----------------------------------------------------------------

export interface Me {
  id: number
  email: string
  firstName: string | null
  lastName: string | null
  tenantId: number
}

export interface DocumentItem {
  id: number
  sothisDocumentId: string
  type: string
  title: string
  state:
    | 'en_attente_signature'
    | 'signature_en_cours'
    | 'signe'
    | 'signe_valide'
    | 'refuse'
    | 'expire'
  deadline: string | null
  createdAt: string
  updatedAt: string
  hasSignedPdf: boolean
}

export const api = {
  async login(email: string, password: string): Promise<{ user: Me; csrfToken: string }> {
    const data = await request<{ user: Me; csrfToken: string }>('/auth/login', {
      method: 'POST',
      body: { email, password },
      withCsrf: false,
    })
    setCsrfToken(data.csrfToken)
    return data
  },

  async logout(): Promise<void> {
    await request<void>('/auth/logout', { method: 'POST' })
    setCsrfToken(null)
  },

  async me(): Promise<Me> {
    const data = await request<{ user: Me }>('/auth/me')
    return data.user
  },

  /**
   * Met a jour l'email et/ou le mot de passe de l'utilisateur connecte.
   * Le mot de passe actuel est obligatoire pour valider l'identite.
   */
  async updateProfile(payload: {
    currentPassword: string
    email?: string
    newPassword?: string
  }): Promise<{ user: Me; updated: { email: boolean; password: boolean } }> {
    return request<{ user: Me; updated: { email: boolean; password: boolean } }>(
      '/auth/profile',
      { method: 'POST', body: payload },
    )
  },

  async listDocuments(): Promise<DocumentItem[]> {
    const data = await request<{ items: DocumentItem[]; count: number }>('/documents')
    return data.items
  },

  async getDocument(id: number): Promise<DocumentItem> {
    return request<DocumentItem>(`/documents/${id}`)
  },

  async startSignature(id: number): Promise<void> {
    await request<unknown>(`/documents/${id}/sign/start`, { method: 'POST' })
  },

  async completeSignature(
    id: number,
    payload: { otp: string; signature: string },
  ): Promise<{ signatureId: number; signedAt: string; state: string }> {
    return request(`/documents/${id}/sign/complete`, {
      method: 'POST',
      body: payload,
    })
  },

  async refuseDocument(id: number, reason: string): Promise<void> {
    await request<unknown>(`/documents/${id}/refuse`, {
      method: 'POST',
      body: { reason },
    })
  },
}
