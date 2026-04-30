import { Inbox } from 'lucide-react'
import { cn } from '@/lib/cn'

interface EmptyStateProps {
  title: string
  description?: string
  className?: string
}

/**
 * Etat vide reutilisable, affiche quand une liste ne contient aucun element.
 */
export function EmptyState({ title, description, className }: EmptyStateProps) {
  return (
    <div
      className={cn(
        'flex flex-col items-center justify-center rounded-lg border border-dashed border-slate-300 bg-white px-6 py-10 text-center',
        'dark:border-brand-700 dark:bg-brand-800',
        className,
      )}
    >
      <Inbox className="mb-3 h-8 w-8 text-slate-400 dark:text-sand-300" />
      <p className="text-sm font-medium text-slate-700 dark:text-sand-50">{title}</p>
      {description && (
        <p className="mt-1 text-sm text-slate-500 dark:text-sand-200">{description}</p>
      )}
    </div>
  )
}
