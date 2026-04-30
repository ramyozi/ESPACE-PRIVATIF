import { Calendar, Download, FileText, FileType2, Hash } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { cn } from '@/lib/cn'
import type { DocumentItem } from '@/services/api'

interface DocumentPreviewCardProps {
  document: DocumentItem
  className?: string
}

type PdfStatus = 'loading' | 'success' | 'error'

/**
 * Carte preview d'un document a signer.
 *
 * Strategie de rendu :
 *  - on monte l'<iframe> directement vers /api/documents/{id}/pdf
 *  - tant que onLoad ne s'est pas declenche, on superpose un skeleton
 *  - si l'iframe ne charge pas en 8 secondes, on bascule en "Apercu indisponible"
 *  - le bouton "Telecharger" est un simple <a download> : le clic est une
 *    navigation top-level, donc le cookie SameSite=None;Secure est envoye
 *    meme cross-origin (ce qui n'est pas garanti pour les fetch+Blob).
 *
 * Aucune sonde fetch prealable : elle peut echouer pour des raisons CORS /
 * 3rd-party-cookies sans que le rendu reel pose probleme. On laisse le
 * navigateur tenter, et on bascule en fallback uniquement si rien ne charge.
 */
export function DocumentPreviewCard({ document: doc, className }: DocumentPreviewCardProps) {
  const [status, setStatus] = useState<PdfStatus>('loading')
  const timerRef = useRef<number | null>(null)

  // Base API : VITE_API_BASE_URL en prod, vide en dev (proxy Vite).
  const apiBase = (import.meta.env.VITE_API_BASE_URL ?? '').replace(/\/+$/, '')
  const pdfUrl = `${apiBase}/api/documents/${doc.id}/pdf`
  const pdfDownloadUrl = `${pdfUrl}?download=1`

  useEffect(() => {
    let cancelled = false
    setStatus('loading')

    // Sonde Content-Type : on demande le PDF en GET et on regarde le header
    // de reponse. Si ce n'est pas un PDF (404 JSON, 401, HTML d'erreur, etc.),
    // on bascule directement en "Apercu indisponible" sans jamais afficher
    // l'iframe (qui sinon rendrait le JSON brut).
    fetch(pdfUrl, { method: 'GET', credentials: 'include' })
      .then((res) => {
        if (cancelled) return
        const ct = (res.headers.get('Content-Type') ?? '').toLowerCase()
        if (res.ok && ct.includes('application/pdf')) {
          setStatus('success')
        } else {
          setStatus('error')
        }
      })
      .catch(() => {
        if (!cancelled) setStatus('error')
      })

    // Garde-fou ultime : si la sonde traine plus de 8s sans repondre,
    // on tombe sur le skeleton "Apercu indisponible" pour ne pas bloquer.
    timerRef.current = window.setTimeout(() => {
      if (cancelled) return
      setStatus((s) => (s === 'loading' ? 'error' : s))
    }, 8000)

    return () => {
      cancelled = true
      if (timerRef.current !== null) window.clearTimeout(timerRef.current)
    }
  }, [pdfUrl])

  function handleIframeError() {
    setStatus('error')
  }

  const deadlineLabel = doc.deadline
    ? new Date(doc.deadline).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
      })
    : null

  function handleDownload() {
    window.open(pdfDownloadUrl, '_blank')
  }

  return (
    <div
      className={cn(
        'flex h-full flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm',
        'dark:border-brand-700 dark:bg-brand-800',
        className,
      )}
    >
      {/* En-tete : titre + bouton telecharger (toujours actif) */}
      <div className="flex items-center gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 dark:border-brand-700 dark:bg-brand-900/40">
        <div className="flex h-9 w-9 items-center justify-center rounded-md bg-blue-100 text-blue-700 dark:bg-brand-700 dark:text-accent-300">
          <FileText className="h-5 w-5" aria-hidden />
        </div>
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-semibold text-slate-900 dark:text-sand-50">{doc.title}</p>
          <p className="text-xs text-slate-500 dark:text-sand-300">{doc.sothisDocumentId}</p>
        </div>
        {/* Lien direct : top-level navigation, cookie cross-origin envoye
            sous SameSite=None+Secure. Plus fiable qu'un fetch+Blob. */}
        <button onClick={handleDownload}>
          <Download className="h-4 w-4" />
        </button> 
      </div>

      {/* Zone preview : iframe affichee uniquement si la sonde a confirme un PDF.
          Sinon on rend le skeleton "loading" ou "Apercu indisponible". */}
      <div className="flex-1 space-y-2 p-5">
        <div className="relative h-[400px] w-full overflow-hidden rounded-md border border-slate-200 bg-white dark:border-brand-700 dark:bg-sand-50">
          {status === 'success' ? (
            <iframe
              src={pdfUrl}
              title={`Apercu du document ${doc.title}`}
              className="h-full w-full bg-white"
              sandbox="allow-same-origin allow-scripts"
              onError={handleIframeError}
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
      {!loading && (
        <p className="mt-3 text-xs text-slate-500 dark:text-sand-300">
          Vous pouvez toujours telecharger le document via le bouton ci-dessus.
        </p>
      )}
    </div>
  )
}
