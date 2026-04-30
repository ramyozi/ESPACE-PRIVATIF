import { Logo } from '@/components/Logo'

/**
 * Pied de page global de l'application.
 * Sobre, sur fond sand pour rester coherent avec le body.
 */
export function Footer() {
  const year = new Date().getFullYear()
  return (
    <footer className="mt-auto border-t border-sand-200 bg-sand-50">
      <div className="container-app flex flex-col items-center justify-between gap-4 py-6 text-sm text-slate-600 sm:flex-row">
        <div className="flex items-center gap-3">
          <Logo size={22} />
          <span className="hidden text-slate-300 sm:inline">|</span>
          <span className="hidden sm:inline">Realsoft Immobilier</span>
        </div>

        <nav className="flex items-center gap-5 text-xs text-slate-500">
          <a href="#" className="transition-colors hover:text-brand-500">Mentions legales</a>
          <a href="#" className="transition-colors hover:text-brand-500">Confidentialite</a>
          <a href="#" className="transition-colors hover:text-brand-500">Aide</a>
        </nav>

        <p className="text-xs text-slate-400">
          &copy; {year} Realsoft. Tous droits reserves.
        </p>
      </div>
    </footer>
  )
}
