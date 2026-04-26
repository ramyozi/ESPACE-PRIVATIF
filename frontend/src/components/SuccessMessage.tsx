import { CheckCircle2 } from 'lucide-react'
import { cn } from '@/lib/cn'

interface SuccessMessageProps {
  message: string
  className?: string
}

/**
 * Encart de succes (signature finalisee, refus enregistre, etc.).
 */
export function SuccessMessage({ message, className }: SuccessMessageProps) {
  return (
    <div
      role="status"
      className={cn(
        'flex items-start gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700',
        className,
      )}
    >
      <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0" />
      <span>{message}</span>
    </div>
  )
}
