import { forwardRef, type InputHTMLAttributes } from 'react'
import { cn } from '@/lib/cn'

type InputProps = InputHTMLAttributes<HTMLInputElement>

export const Input = forwardRef<HTMLInputElement, InputProps>(
  ({ className, ...props }, ref) => {
    return (
      <input
        ref={ref}
        className={cn(
          'h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm',
          'placeholder:text-slate-400',
          'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:border-brand-500',
          'disabled:cursor-not-allowed disabled:opacity-60',
          // Dark
          'dark:border-brand-700 dark:bg-brand-800 dark:text-sand-50',
          'dark:placeholder:text-sand-300/60',
          'dark:focus-visible:ring-accent-400 dark:focus-visible:border-accent-400',
          className,
        )}
        {...props}
      />
    )
  },
)
Input.displayName = 'Input'
