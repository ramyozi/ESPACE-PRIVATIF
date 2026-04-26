import { clsx, type ClassValue } from 'clsx'
import { twMerge } from 'tailwind-merge'

/**
 * Helper de fusion de classes Tailwind, dans l esprit shadcn/ui.
 * Combine clsx (classes conditionnelles) et tailwind-merge (deduplication).
 */
export function cn(...inputs: ClassValue[]): string {
  return twMerge(clsx(inputs))
}
