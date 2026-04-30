import { Calendar, Download, FileText, FileType2, Hash } from 'lucide-react'
import { useEffect, useState } from 'react'
import { cn } from '@/lib/cn'
import { api, type DocumentItem } from '@/services/api'

interface DocumentPreviewCardProps {
  document: DocumentItem
  className?: string
}

type PdfStatus = 'loading' | 'ready' | 'error'

/**
 * Carte preview d'un document a signer.
 *
 * Strategie d'auth :
 *  - on demande au backend un token court (60s) via /pdf-token (proteg par
 *    session, donc reutilise le cookie de l'app sans probleme cross-origin
 *    car c'est une requete fetch JSON classique avec credentials)
 *  - on construit ensuite l'URL du PDF avec ?token=... : iframe et bouton
 *    de telechargement n'ont alors PLUS BESOIN du cookie. Plus aucun souci
 *    de 3rd-party-cookies, SameSite, etc.
 *
 * Tant que le token n'est pas obtenu : skeleton "Chargement de l'apercu".
 * Si l'obtention du token echoue : skeleton "Apercu indisponible" + bouton
 * de telechargement desactive.
 */
export function DocumentPreviewCard({ document: doc, className }: DocumentPreviewCardProps) {
  const [status, setStatus] = useState<PdfStatus>('loading')
  const [token, setToken] = useState<string | null>(null)

  // Base API : VITE_API_BASE_URL en prod, vide en dev (proxy Vite).
  const apiBase = (import.meta.env.VITE_API_BASE_URL ?? '').replace(/\/+$/, '')

  useEffect(() => {
    let cancelled = false
    setStatus('loading')
    setToken(null)

    api
      .getPdfToken(doc.id)
      .then(({ token }) => {
        if (cancelled) return
        setToken(token)
        setStatus('ready')
      })
      .catch(() => {
        if (!cancelled) setStatus('error')
      })

    return () => {
      cancelled = true
    }
  }, [doc.id])

  // URLs construites une fois le token recu.
  const pdfUrl = token ? `${apiBase}/api/documents/${doc.id}/pdf?token=${encodeURIComponent(token)}` : null
  const pdfDownloadUrl = pdfUrl ? `${pdfUrl}&download=1` : null

  const deadlineLabel = doc.deadline
    ? new Date(doc.deadline).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
      })
    : null

  // Nom propose au navigateur (le backend pose deja un Content-Disposition propre).
  const downloadName = (doc.title.replace(/[^A-Za-z0-9._-]+/g, '-') || 'document') + '.pdf'

  return (
    <div
      className={cn(
        'flex h-full flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm',
        'dark:border-brand-700 dark:bg-brand-800',
        className,
      )}
    >
      {/* En-tete : titre + bouton telecharger */}
      <div className="flex items-center gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 dark:border-brand-700 dark:bg-brand-900/40">
        <div className="flex h-9 w-9 items-center justify-center rounded-md bg-blue-100 text-blue-700 dark:bg-brand-700 dark:text-accent-300">
          <FileText className="h-5 w-5" aria-hidden />
        </div>
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-semibold text-slate-900 dark:text-sand-50">{doc.title}</p>
          <p className="text-xs text-slate-500 dark:text-sand-300">{doc.sothisDocumentId}</p>
        </div>
        {pdfDownloadUrl ? (
          <a
            href={pdfDownloadUrl}
            download={downloadName}
            target="_blank"
            rel="noopener noreferrer"
            title="Telecharger le document"
            className="inline-flex h-9 items-center justify-center gap-1.5 rounded-md px-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-sand-100 dark:hover:bg-brand-700"
          >
            <Download className="h-4 w-4" aria-hidden />
            <span className="hidden sm:inline">Telecharger</span>
          </a>
        ) : (
          <span
            title="Disponible quand l'apercu est charge"
            className="inline-flex h-9 cursor-not-allowed items-center justify-center gap-1.5 rounded-md px-3 text-sm font-medium text-slate-400 dark:text-sand-300/60"
          >
            <Download className="h-4 w-4" aria-hidden />
            <span className="hidden sm:inline">Telecharger</span>
          </span>
        )}
      </div>

      {/* Zone preview : iframe avec token URL des qu'on l'a, sinon skeleton */}
      <div className="flex-1 space-y-2 p-5">
        <div className="relative h-[400px] w-full overflow-hidden rounded-md border border-slate-200 bg-white dark:border-brand-700 dark:bg-sand-50">
          {status === 'ready' && pdfUrl ? (
            <iframe
              src={pdfUrl}
              title={`Apercu du document ${doc.title}`}
              className="h-full w-full bg-white"
            />
          ) : (
            <div className="absolute inset-0 flex items-center justify-center p-4">
              <PreviewSkeleton loading={status === 'loading'} />
            </div>
          )}
        </div>

        {/* Meta-donnees du document */}
        <dl className="mt-4 space-y-2 text-sm">
          <div className="flex items-center gap-2 text-slate-600 dark:text-sand-200">
            <FileType2 className="h-4 w-4 text-slate-400 dark:text-sand-300" aria-hidden />
            <dt className="sr-only">Type</dt>
            <dd>
              <span className="text-slate-500 dark:text-sand-300">Type :</span>{' '}
              <span className="font-medium capitalize text-slate-800 dark:text-sand-50">{doc.type}</span>
            </dd>
          </div>
          <div className="flex items-center gap-2 text-slate-600 dark:text-sand-200">
            <Hash className="h-4 w-4 text-slate-400 dark:text-sand-300" aria-hidden />
            <dt className="sr-only">Reference</dt>
            <dd className="truncate">
              <span className="text-slate-500 dark:text-sand-300">Reference :</span>{' '}
              <span className="font-mono text-xs text-slate-700 dark:text-sand-100">{doc.sothisDocumentId}</span>
            </dd>
          </div>
          {deadlineLabel && (
            <div className="flex items-center gap-2 text-slate-600 dark:text-sand-200">
              <Calendar className="h-4 w-4 text-slate-400 dark:text-sand-300" aria-hidden />
              <dt className="sr-only">Echeance</dt>
              <dd>
                <span className="text-slate-500 dark:text-sand-300">A signer avant le :</span>{' '}
                <span className="font-medium text-slate-800 dark:text-sand-50">{deadlineLabel}</span>
              </dd>
            </div>
          )}
        </dl>
      </div>
    </div>
  )
}

/**
 * Squelette d'apercu : pulse pendant le chargement, message discret en
 * fallback d'erreur. Aucune dependance, aucun crash possible.
 */
function PreviewSkeleton({ loading }: { loading: boolean }) {
  return (
    <div
      className={cn(
        'w-full rounded-md border border-dashed border-slate-200 bg-slate-50/80 p-4',
        'dark:border-brand-700 dark:bg-brand-800/60',
        loading && 'animate-pulse',
      )}
      aria-busy={loading}
    >
      <p className="mb-3 text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-sand-300">
        {loading ? "Chargement de l'apercu" : 'Apercu indisponible'}
      </p>
      <div className="space-y-1.5" aria-hidden>
        <div className="h-2 w-3/4 rounded bg-slate-200 dark:bg-brand-700" />
        <div className="h-2 w-full rounded bg-slate-200 dark:bg-brand-700" />
        <div className="h-2 w-5/6 rounded bg-slate-200 dark:bg-brand-700" />
        <div className="h-2 w-2/3 rounded bg-slate-200 dark:bg-brand-700" />
        <div className="my-3" />
        <div className="h-2 w-full rounded bg-slate-200 dark:bg-brand-700" />
        <div className="h-2 w-11/12 rounded bg-slate-200 dark:bg-brand-700" />
        <div className="h-2 w-3/4 rounded bg-slate-200 dark:bg-brand-700" />
      </div>
    </div>
  )
}
