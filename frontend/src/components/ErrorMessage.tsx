import { AlertCircle } from 'lucide-react'
import { cn } from '@/lib/cn'

interface ErrorMessageProps {
  message: string
  className?: string
}

/**
 * Encart d'erreur standard, utilise dans les formulaires et les pages.
 */
export function ErrorMessage({ message, className }: ErrorMessageProps) {
  return (
    <div
      role="alert"
      className={cn(
        'flex items-start gap-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700',
        'dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300',
        className,
      )}
    >
      <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
      <span>{message}</span>
    </div>
  )
}
