import { cn } from '@/lib/cn'

interface LoaderProps {
  /** Taille en pixels (par defaut 24) */
  size?: number
  /** Couleur de l'anneau, par defaut le brand */
  className?: string
  label?: string
}

/**
 * Spinner circulaire utilise pour les chargements.
 * On masque visuellement le texte mais on le rend disponible pour les lecteurs d'ecran.
 */
export function Loader({ size = 24, className, label = 'Chargement en cours' }: LoaderProps) {
  return (
    <span
      role="status"
      aria-label={label}
      className={cn('inline-block', className)}
      style={{ width: size, height: size }}
    >
      <svg
        className="animate-spin text-brand-600"
        style={{ width: size, height: size }}
        viewBox="0 0 24 24"
        fill="none"
      >
        <circle cx="12" cy="12" r="10" stroke="currentColor" strokeOpacity="0.25" strokeWidth="4" />
        <path
          d="M4 12a8 8 0 018-8"
          stroke="currentColor"
          strokeWidth="4"
          strokeLinecap="round"
        />
      </svg>
      <span className="sr-only">{label}</span>
    </span>
  )
}
