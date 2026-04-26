import type { DocumentItem } from '@/services/api'

/**
 * Libelles utilisateur pour chaque etat du document.
 * Centralise pour rester coherent sur toutes les pages.
 */
export const stateLabels: Record<DocumentItem['state'], string> = {
  en_attente_signature: 'A signer',
  signature_en_cours: 'Signature en cours',
  signe: 'Signe (en attente de validation)',
  signe_valide: 'Signe et valide',
  refuse: 'Refuse',
  expire: 'Expire',
}

/**
 * Classes Tailwind pour le badge correspondant.
 */
export const stateBadgeClasses: Record<DocumentItem['state'], string> = {
  en_attente_signature: 'bg-amber-100 text-amber-800',
  signature_en_cours: 'bg-blue-100 text-blue-800',
  signe: 'bg-indigo-100 text-indigo-800',
  signe_valide: 'bg-emerald-100 text-emerald-800',
  refuse: 'bg-red-100 text-red-800',
  expire: 'bg-slate-200 text-slate-700',
}

/**
 * Formatte une date ISO en format court francais.
 */
export function formatDate(iso: string | null | undefined): string {
  if (!iso) return ''
  try {
    return new Date(iso).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    })
  } catch {
    return iso
  }
}
