import { cn } from '@/lib/cn'
import { stateBadgeClasses, stateLabels } from '@/lib/documentState'
import type { DocumentItem } from '@/services/api'

interface StateBadgeProps {
  state: DocumentItem['state']
  className?: string
}

/**
 * Badge colore representant l'etat d'un document.
 */
export function StateBadge({ state, className }: StateBadgeProps) {
  return (
    <span
      className={cn(
        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
        stateBadgeClasses[state],
        className,
      )}
    >
      {stateLabels[state]}
    </span>
  )
}
