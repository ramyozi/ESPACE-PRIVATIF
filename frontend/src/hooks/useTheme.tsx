import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react'

export type ThemeMode = 'light' | 'dark' | 'system'
export type ResolvedTheme = 'light' | 'dark'

interface ThemeContextValue {
  /** Mode souhaite par l'utilisateur (light, dark ou system). */
  mode: ThemeMode
  /** Theme effectivement applique (resolution de "system"). */
  resolved: ResolvedTheme
  /** Met a jour le mode et persiste le choix en localStorage. */
  setMode: (mode: ThemeMode) => void
  /** Bascule rapidement entre light et dark (raccourci pour le toggle). */
  toggle: () => void
}

const STORAGE_KEY = 'ep-theme'
const ThemeContext = createContext<ThemeContextValue | undefined>(undefined)

function readSystemPrefersDark(): boolean {
  if (typeof window === 'undefined') return false
  return window.matchMedia('(prefers-color-scheme: dark)').matches
}

function readStoredMode(): ThemeMode {
  if (typeof window === 'undefined') return 'system'
  const v = window.localStorage.getItem(STORAGE_KEY)
  return v === 'dark' || v === 'light' ? v : 'system'
}

function applyClass(resolved: ResolvedTheme): void {
  const root = document.documentElement
  if (resolved === 'dark') root.classList.add('dark')
  else root.classList.remove('dark')
}

/**
 * Provider qui :
 *  - lit le mode persiste (sinon "system")
 *  - resout vers "light" ou "dark"
 *  - reagit aux changements systeme tant que le mode est "system"
 *  - synchronise la classe `dark` sur <html> a chaque changement
 *
 * Le script anti-FOUC dans index.html applique deja la classe avant que React
 * ne monte, donc pas de flash visuel a l'ouverture.
 */
export function ThemeProvider({ children }: { children: ReactNode }) {
  const [mode, setModeState] = useState<ThemeMode>(() => readStoredMode())
  const [resolved, setResolved] = useState<ResolvedTheme>(() => {
    const stored = readStoredMode()
    if (stored === 'dark') return 'dark'
    if (stored === 'light') return 'light'
    return readSystemPrefersDark() ? 'dark' : 'light'
  })

  // Recalcule le theme effectif en fonction du mode courant.
  useEffect(() => {
    const next: ResolvedTheme =
      mode === 'system' ? (readSystemPrefersDark() ? 'dark' : 'light') : mode
    setResolved(next)
    applyClass(next)
  }, [mode])

  // Si le mode est "system", on suit les changements OS en temps reel.
  useEffect(() => {
    if (mode !== 'system' || typeof window === 'undefined') return
    const mq = window.matchMedia('(prefers-color-scheme: dark)')
    const onChange = () => {
      const next: ResolvedTheme = mq.matches ? 'dark' : 'light'
      setResolved(next)
      applyClass(next)
    }
    mq.addEventListener('change', onChange)
    return () => mq.removeEventListener('change', onChange)
  }, [mode])

  const setMode = useCallback((next: ThemeMode) => {
    setModeState(next)
    try {
      if (next === 'system') window.localStorage.removeItem(STORAGE_KEY)
      else window.localStorage.setItem(STORAGE_KEY, next)
    } catch {
      // localStorage indisponible : on garde quand meme l'etat memoire
    }
  }, [])

  const toggle = useCallback(() => {
    setMode(resolved === 'dark' ? 'light' : 'dark')
  }, [resolved, setMode])

  const value = useMemo<ThemeContextValue>(
    () => ({ mode, resolved, setMode, toggle }),
    [mode, resolved, setMode, toggle],
  )

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>
}

export function useTheme(): ThemeContextValue {
  const ctx = useContext(ThemeContext)
  if (!ctx) throw new Error('useTheme doit etre utilise dans un ThemeProvider')
  return ctx
}
