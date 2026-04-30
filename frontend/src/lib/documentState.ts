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
  en_attente_signature: 'bg-accent-100 text-accent-700 ring-1 ring-accent-200',
  signature_en_cours: 'bg-brand-50 text-brand-600 ring-1 ring-brand-100',
  signe: 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-100',
  signe_valide: 'bg-success-50 text-success-700 ring-1 ring-emerald-200',
  refuse: 'bg-danger-50 text-danger-700 ring-1 ring-rose-200',
  expire: 'bg-sand-100 text-slate-600 ring-1 ring-sand-200',
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
