import { cn } from '@/lib/cn'

interface SkeletonProps {
  className?: string
}

/**
 * Bloc de chargement style "shimmer" simple.
 * Anime un fond gris clair avec une animation Tailwind native.
 */
export function Skeleton({ className }: SkeletonProps) {
  return (
    <div
      className={cn('animate-pulse rounded-md bg-slate-200', className)}
      aria-hidden
    />
  )
}
