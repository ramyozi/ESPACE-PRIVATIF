import { Moon, Sun } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useTheme } from '@/hooks/useTheme'
import { cn } from '@/lib/cn'

interface ThemeToggleProps {
  className?: string
}

/**
 * Bouton de bascule clair / sombre.
 * Affiche l'icone du theme VERS LEQUEL on bascule (pattern UX habituel).
 * - en clair : icone lune (cliquer = passer en sombre)
 * - en sombre : icone soleil (cliquer = passer en clair)
 */
export function ThemeToggle({ className }: ThemeToggleProps) {
  const { resolved, toggle } = useTheme()
  const isDark = resolved === 'dark'

  return (
    <Button
      type="button"
      variant="ghost"
      size="sm"
      onClick={toggle}
      title={isDark ? 'Passer en mode clair' : 'Passer en mode sombre'}
      aria-label={isDark ? 'Passer en mode clair' : 'Passer en mode sombre'}
      className={cn(
        'text-slate-600 hover:bg-sand-100 hover:text-ink',
        'dark:text-sand-200 dark:hover:bg-brand-700 dark:hover:text-white',
        className,
      )}
    >
      {isDark ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
    </Button>
  )
}
