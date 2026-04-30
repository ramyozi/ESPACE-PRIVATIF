import { cn } from '@/lib/cn'

interface LogoProps {
  /** Affiche le wordmark a cote de la marque (par defaut true). */
  withText?: boolean
  /** Taille de l'icone en px (et echelle proportionnelle du texte). */
  size?: number
  className?: string
  /** Variante claire pour fond fonce. */
  variant?: 'default' | 'light'
}

/**
 * Logo Realsoft Espace Privatif.
 *
 * SVG inline pour rester nette en haute densite et evite une requete reseau.
 * Forme : un "document a signer" stylise (cadre + ligne de signature)
 * dans un carre arrondi aux couleurs de la marque.
 */
export function Logo({ withText = true, size = 32, className, variant = 'default' }: LogoProps) {
  const isLight = variant === 'light'
  const bg = isLight ? '#FFFFFF' : '#1F3A4D'
  const stroke = '#D4A24C'
  const textColor = isLight ? 'text-white' : 'text-brand-500'

  return (
    <div className={cn('inline-flex items-center gap-2.5', className)}>
      <svg
        width={size}
        height={size}
        viewBox="0 0 32 32"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
        aria-hidden
      >
        <rect width="32" height="32" rx="7" fill={bg} />
        {/* Cadre du document */}
        <path
          d="M9 8h10a4 4 0 0 1 4 4v8a4 4 0 0 1-4 4H9V8z"
          fill="none"
          stroke={stroke}
          strokeWidth="2.4"
          strokeLinejoin="round"
        />
        {/* Ligne de signature */}
        <path
          d="M9 16h10"
          stroke={stroke}
          strokeWidth="2.4"
          strokeLinecap="round"
        />
        {/* Petit accent : trait de signature */}
        <path
          d="M11 19c1.5-0.8 2.7-0.4 3.4 0.4"
          stroke={stroke}
          strokeWidth="1.6"
          strokeLinecap="round"
          opacity="0.7"
        />
      </svg>

      {withText && (
        <span className={cn('font-display text-base font-bold tracking-tight', textColor)}>
          Espace<span className="font-medium opacity-80"> Privatif</span>
        </span>
      )}
    </div>
  )
}
