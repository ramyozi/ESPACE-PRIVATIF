import { Calendar, Download, FileText, FileType2, Hash, Loader2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Button } from '@/components/ui/button'
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
 *  - on tente de charger le PDF via l'endpoint authentifie
 *    GET /api/documents/{id}/pdf (filtre tenant + user cote backend)
 *  - si la sonde HEAD reussit, on affiche le PDF dans un <iframe>
 *  - sinon (404, CORS, reseau), on retombe sur un skeleton sobre
 *  - le rendu n'est jamais cassant : pas de crash, juste un fallback
 */
export function DocumentPreviewCard({ document, className }: DocumentPreviewCardProps) {
  const [status, setStatus] = useState<PdfStatus>('loading')
  const [downloading, setDownloading] = useState(false)

  // Base API : VITE_API_BASE_URL en prod, vide en dev (proxy Vite).
  const apiBase = (import.meta.env.VITE_API_BASE_URL ?? '').replace(/\/+$/, '')
  const pdfUrl = `${apiBase}/api/documents/${document.id}/pdf`
  const pdfDownloadUrl = `${pdfUrl}?download=1`

  /**
   * Telechargement via fetch + Blob plutot qu'un simple <a download>.
   * Avantage : on conserve l'auth par cookie cross-origin et on peut
   * proposer un nom de fichier propre cote client.
   */
  async function handleDownload() {
    if (status !== 'success' || downloading) return
    setDownloading(true)
    try {
      const res = await fetch(pdfDownloadUrl, {
        method: 'GET',
        credentials: 'include',
      })
      if (!res.ok) throw new Error('download_failed')
      const blob = await res.blob()
      const url = URL.createObjectURL(blob)
      const a = window.document.createElement('a')
      a.href = url
      // Slug local (le backend pose deja un Content-Disposition propre,
      // ceci sert juste de fallback si le navigateur ne lit pas le header).
      a.download = (document.title.replace(/[^A-Za-z0-9._-]+/g, '-') || 'document') + '.pdf'
      window.document.body.appendChild(a)
      a.click()
      window.document.body.removeChild(a)
      URL.revokeObjectURL(url)
    } catch {
      // Erreur silencieuse : on n'interrompt pas la signature pour autant.
      // L'utilisateur peut reessayer ou continuer la signature.
    } finally {
      setDownloading(false)
    }
  }

  useEffect(() => {
    let cancelled = false
    setStatus('loading')

    fetch(pdfUrl, { method: 'GET', credentials: 'include' })
      .then(async (res) => {
        if (cancelled) return
        if (!res.ok) {
          setStatus('error')
          return
        }
        const ct = res.headers.get('Content-Type') ?? ''
        // On exige bien un PDF : si le backend renvoie du JSON (erreur)
        // ou un HTML d'erreur, on bascule sur le skeleton.
        if (!ct.toLowerCase().includes('application/pdf')) {
          setStatus('error')
          return
        }
        setStatus('success')
      })
      .catch(() => {
        if (!cancelled) setStatus('error')
      })

    return () => {
      cancelled = true
    }
  }, [pdfUrl])

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
      {/* En-tete avec icone PDF + bouton telecharger */}
      <div className="flex items-center gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3">
        <div className="flex h-9 w-9 items-center justify-center rounded-md bg-blue-100 text-blue-700">
          <FileText className="h-5 w-5" aria-hidden />
        </div>
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-semibold text-slate-900">{document.title}</p>
          <p className="text-xs text-slate-500">{document.sothisDocumentId}</p>
        </div>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          onClick={handleDownload}
          disabled={status !== 'success' || downloading}
          title={
            status === 'success'
              ? 'Telecharger le document'
              : "PDF indisponible pour le moment"
          }
        >
          {downloading ? (
            <Loader2 className="h-4 w-4 animate-spin" aria-hidden />
          ) : (
            <Download className="h-4 w-4" aria-hidden />
          )}
          <span className="hidden sm:inline">Telecharger</span>
        </Button>
      </div>

      {/* Zone preview : iframe si OK, skeleton sinon (loading et error) */}
      <div className="flex-1 space-y-2 p-5">
        {status === 'success' ? (
          <iframe
            src={pdfUrl}
            title={`Apercu du document ${document.title}`}
            className="h-[400px] w-full rounded-md border border-slate-200 bg-white"
          />
        ) : (
          <PreviewSkeleton loading={status === 'loading'} />
        )}

        {/* Meta-donnees du document */}
        <dl className="mt-4 space-y-2 text-sm">
          <div className="flex items-center gap-2 text-slate-600">
            <FileType2 className="h-4 w-4 text-slate-400" aria-hidden />
            <dt className="sr-only">Type</dt>
            <dd>
              <span className="text-slate-500">Type :</span>{' '}
              <span className="font-medium text-slate-800 capitalize">{document.type}</span>
            </dd>
          </div>
          <div className="flex items-center gap-2 text-slate-600">
            <Hash className="h-4 w-4 text-slate-400" aria-hidden />
            <dt className="sr-only">Reference</dt>
            <dd className="truncate">
              <span className="text-slate-500">Reference :</span>{' '}
              <span className="font-mono text-xs text-slate-700">{document.sothisDocumentId}</span>
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

/**
 * Squelette d'apercu utilise pendant le chargement et en fallback d'erreur.
 * Aucune dependance, aucun crash possible.
 */
function PreviewSkeleton({ loading }: { loading: boolean }) {
  return (
    <div
      className={cn(
        'rounded-md border border-dashed border-slate-200 bg-slate-50/60 p-4',
        loading && 'animate-pulse',
      )}
      aria-busy={loading}
    >
      <p className="mb-3 text-xs font-medium uppercase tracking-wider text-slate-400">
        {loading ? 'Chargement de l\'apercu' : 'Apercu indisponible'}
      </p>
      <div className="space-y-1.5" aria-hidden>
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
  )
}
