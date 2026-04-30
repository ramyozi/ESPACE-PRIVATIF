import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react'
import { api, ApiError, setCsrfToken, type Me } from '@/services/api'

interface AuthState {
  user: Me | null
  initializing: boolean
  /** Retourne le user authentifie sur succes (utile pour decider de la landing). */
  login: (email: string, password: string) => Promise<Me>
  logout: () => Promise<void>
  /** Recharge l'utilisateur depuis /auth/me (utile apres update profil). */
  refresh: () => Promise<void>
}

const AuthContext = createContext<AuthState | undefined>(undefined)

/**
 * Fournit l'utilisateur authentifie a toute l'app.
 * Au premier mount, on tente /auth/me pour reprendre une session existante
 * (cookie navigateur encore valide).
 */
export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<Me | null>(null)
  const [initializing, setInitializing] = useState(true)

  useEffect(() => {
    let cancelled = false
    api
      .me()
      .then((me) => {
        if (!cancelled) setUser(me)
      })
      .catch((e) => {
        // 401 = pas connecte, c'est normal
        if (!(e instanceof ApiError) || e.status !== 401) {
          // eslint-disable-next-line no-console
          console.warn('Echec /auth/me', e)
        }
      })
      .finally(() => {
        if (!cancelled) setInitializing(false)
      })
    return () => {
      cancelled = true
    }
  }, [])

  const login = useCallback(async (email: string, password: string) => {
    const result = await api.login(email, password)
    setUser(result.user)
    return result.user
  }, [])

  const logout = useCallback(async () => {
    try {
      await api.logout()
    } finally {
      setUser(null)
      setCsrfToken(null)
    }
  }, [])

  const refresh = useCallback(async () => {
    try {
      const me = await api.me()
      setUser(me)
    } catch {
      // Si le rafraichissement echoue, on laisse l'etat tel quel.
    }
  }, [])

  const value = useMemo<AuthState>(
    () => ({ user, initializing, login, logout, refresh }),
    [user, initializing, login, logout, refresh],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthState {
  const ctx = useContext(AuthContext)
  if (!ctx) {
    throw new Error('useAuth doit etre utilise a l interieur d un AuthProvider')
  }
  return ctx
}
