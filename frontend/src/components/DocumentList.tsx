import { Link } from 'react-router-dom'
import { ArrowRight, FileText } from 'lucide-react'
import { Card } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { StateBadge } from '@/components/StateBadge'
import { formatDate } from '@/lib/documentState'
import type { DocumentItem } from '@/services/api'

interface DocumentListProps {
  documents: DocumentItem[]
}

/**
 * Liste des documents du locataire.
 * Affichee sous forme de cartes cliquables, chaque ligne menant au detail.
 */
export function DocumentList({ documents }: DocumentListProps) {
  return (
    <ul className="space-y-3">
      {documents.map((doc) => (
        <li key={doc.id}>
          <Link
            to={`/documents/${doc.id}`}
            className="group block rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
          >
            <Card className="flex items-center gap-4 border-sand-200 bg-white p-5 shadow-soft transition-all hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-card">
              <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-500 transition-colors group-hover:bg-brand-500 group-hover:text-white">
                <FileText className="h-5 w-5" />
              </div>
              <div className="min-w-0 flex-1">
                <div className="flex items-center justify-between gap-3">
                  <p className="truncate font-display text-base font-semibold text-ink">{doc.title}</p>
                  <StateBadge state={doc.state} />
                </div>
                <p className="mt-1 text-xs text-slate-500">
                  <span className="font-mono">{doc.sothisDocumentId}</span>
                  {doc.deadline && (
                    <span className="ml-3 text-slate-400">
                      A signer avant le {formatDate(doc.deadline)}
                    </span>
                  )}
                </p>
              </div>
              <ArrowRight className="h-4 w-4 shrink-0 text-slate-400 transition-transform group-hover:translate-x-0.5 group-hover:text-brand-500" />
            </Card>
          </Link>
        </li>
      ))}
    </ul>
  )
}

/**
 * Skeleton affiche pendant le chargement initial de la liste.
 * Trois lignes simulees, suffisant pour donner un retour visuel.
 */
export function DocumentListSkeleton() {
  return (
    <ul className="space-y-3" aria-hidden>
      {[0, 1, 2].map((i) => (
        <li key={i}>
          <Card className="flex items-center gap-4 p-4">
            <Skeleton className="h-10 w-10 rounded-md" />
            <div className="flex-1 space-y-2">
              <Skeleton className="h-4 w-2/3" />
              <Skeleton className="h-3 w-1/3" />
            </div>
            <Skeleton className="h-5 w-20 rounded-full" />
          </Card>
        </li>
      ))}
    </ul>
  )
}
