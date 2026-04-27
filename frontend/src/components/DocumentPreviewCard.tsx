import { FileText, Calendar, Hash, FileType2 } from 'lucide-react'
import { cn } from '@/lib/cn'
import type { DocumentItem } from '@/services/api'

interface DocumentPreviewCardProps {
  document: DocumentItem
  className?: string
}

/**
 * Carte "preview" du document a signer.
 *
 * On ne charge pas le PDF brut ici (le backend n'expose pas l'URL publique
 * directement, c'est volontaire). On affiche a la place un visuel sobre :
 *  - en-tete style "page de garde PDF" avec titre et reference SOTHIS
 *  - meta-donnees (type, deadline)
 *  - lignes simulees pour evoquer un document
 *
 * L'idee est de donner un repere visuel au locataire pendant la signature,
 * sans complexifier l'integration en V1.
 */
export function DocumentPreviewCard({ document, className }: DocumentPreviewCardProps) {
  const deadlineLabel = document.deadline
    ? new Date(document.deadline).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
      })
    : null

  return (
    <div
      className={cn(
        'flex h-full flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm',
        className,
      )}
    >
      {/* En-tete avec icone PDF */}
      <div className="flex items-center gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3">
        <div className="flex h-9 w-9 items-center justify-center rounded-md bg-blue-100 text-blue-700">
          <FileText className="h-5 w-5" aria-hidden />
        </div>
        <div className="min-w-0">
          <p className="truncate text-sm font-semibold text-slate-900">
            {document.title}
          </p>
          <p className="text-xs text-slate-500">{document.sothisDocumentId}</p>
        </div>
      </div>

      {/* Zone de preview simulee : lignes de texte */}
      <div className="flex-1 space-y-2 p-5">
        <div className="rounded-md border border-dashed border-slate-200 bg-slate-50/60 p-4">
          <p className="mb-3 text-xs font-medium uppercase tracking-wider text-slate-400">
            Apercu
          </p>
          <div className="space-y-1.5" aria-hidden>
            {/* Lignes de texte simulees pour evoquer une page de document */}
            <div className="h-2 w-3/4 rounded bg-slate-200" />
            <div className="h-2 w-full rounded bg-slate-200" />
            <div className="h-2 w-5/6 rounded bg-slate-200" />
            <div className="h-2 w-2/3 rounded bg-slate-200" />
            <div className="my-3" />
            <div className="h-2 w-full rounded bg-slate-200" />
            <div className="h-2 w-11/12 rounded bg-slate-200" />
            <div className="h-2 w-3/4 rounded bg-slate-200" />
          </div>
        </div>

        {/* Meta-donnees du document */}
        <dl className="mt-4 space-y-2 text-sm">
          <div className="flex items-center gap-2 text-slate-600">
            <FileType2 className="h-4 w-4 text-slate-400" aria-hidden />
            <dt className="sr-only">Type</dt>
            <dd>
              <span className="text-slate-500">Type :</span>{' '}
              <span className="font-medium text-slate-800 capitalize">
                {document.type}
              </span>
            </dd>
          </div>
          <div className="flex items-center gap-2 text-slate-600">
            <Hash className="h-4 w-4 text-slate-400" aria-hidden />
            <dt className="sr-only">Reference</dt>
            <dd className="truncate">
              <span className="text-slate-500">Reference :</span>{' '}
              <span className="font-mono text-xs text-slate-700">
                {document.sothisDocumentId}
              </span>
            </dd>
          </div>
          {deadlineLabel && (
            <div className="flex items-center gap-2 text-slate-600">
              <Calendar className="h-4 w-4 text-slate-400" aria-hidden />
              <dt className="sr-only">Echeance</dt>
              <dd>
                <span className="text-slate-500">A signer avant le :</span>{' '}
                <span className="font-medium text-slate-800">{deadlineLabel}</span>
              </dd>
            </div>
          )}
        </dl>
      </div>
    </div>
  )
}
