import { Logo } from '@/components/Logo'
import { useTheme } from '@/hooks/useTheme'

/**
 * Pied de page global de l'application.
 * Sobre, sur fond sand en clair / brand profond en sombre.
 */
export function Footer() {
  const year = new Date().getFullYear()
  const { resolved } = useTheme()
  return (
    <footer className="mt-auto border-t border-sand-200 bg-sand-50 dark:border-brand-700 dark:bg-brand-900">
      <div className="container-app flex flex-col items-center justify-between gap-4 py-6 text-sm text-slate-600 dark:text-sand-200 sm:flex-row">
        <div className="flex items-center gap-3">
          <Logo size={22} variant={resolved === 'dark' ? 'light' : 'default'} />
          <span className="hidden text-slate-300 dark:text-brand-700 sm:inline">|</span>
          <span className="hidden sm:inline">Realsoft Immobilier</span>
        </div>

        <nav className="flex items-center gap-5 text-xs text-slate-500 dark:text-sand-300">
          <a href="#" className="transition-colors hover:text-brand-500 dark:hover:text-accent-300">
            Mentions legales
          </a>
          <a href="#" className="transition-colors hover:text-brand-500 dark:hover:text-accent-300">
            Confidentialite
          </a>
          <a href="#" className="transition-colors hover:text-brand-500 dark:hover:text-accent-300">
            Aide
          </a>
        </nav>

        <p className="text-xs text-slate-400 dark:text-sand-300/70">
          &copy; {year} Realsoft. Tous droits reserves.
        </p>
      </div>
    </footer>
  )
}
