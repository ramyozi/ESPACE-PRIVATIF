import { forwardRef, type ButtonHTMLAttributes } from 'react'
import { cn } from '@/lib/cn'

type Variant = 'primary' | 'secondary' | 'destructive' | 'ghost'
type Size = 'sm' | 'md' | 'lg'

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant
  size?: Size
}

// Variantes inspirees de shadcn/ui, simplifiees au minimum utile.
// Chaque variante decline un equivalent dark.
const variantStyles: Record<Variant, string> = {
  primary: cn(
    'bg-brand-600 text-white hover:bg-brand-700 disabled:bg-brand-600/60',
    'dark:bg-accent-500 dark:text-brand-900 dark:hover:bg-accent-400 dark:disabled:bg-accent-500/60',
  ),
  secondary: cn(
    'bg-slate-200 text-slate-900 hover:bg-slate-300 disabled:opacity-60',
    'dark:bg-brand-700 dark:text-sand-50 dark:hover:bg-brand-600',
  ),
  destructive: cn(
    'bg-red-600 text-white hover:bg-red-700 disabled:opacity-60',
    'dark:bg-danger-500 dark:hover:bg-danger-700',
  ),
  ghost: cn(
    'bg-transparent text-slate-700 hover:bg-slate-100 disabled:opacity-60',
    'dark:text-sand-100 dark:hover:bg-brand-700',
  ),
}

const sizeStyles: Record<Size, string> = {
  sm: 'h-8 px-3 text-sm',
  md: 'h-10 px-4 text-sm',
  lg: 'h-11 px-5 text-base',
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant = 'primary', size = 'md', ...props }, ref) => {
    return (
      <button
        ref={ref}
        className={cn(
          'inline-flex items-center justify-center gap-2 rounded-md font-medium transition-colors',
          'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2',
          'dark:focus-visible:ring-accent-400 dark:focus-visible:ring-offset-brand-900',
          'disabled:cursor-not-allowed',
          variantStyles[variant],
          sizeStyles[size],
          className,
        )}
        {...props}
      />
    )
  },
)
Button.displayName = 'Button'
