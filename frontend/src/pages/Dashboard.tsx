import { useEffect, useState } from 'react'
import { Layout } from '@/components/Layout'
import { DocumentList, DocumentListSkeleton } from '@/components/DocumentList'
import { EmptyState } from '@/components/EmptyState'
import { ErrorMessage } from '@/components/ErrorMessage'
import { ApiError, api, type DocumentItem } from '@/services/api'

/**
 * Tableau de bord : liste des documents du locataire connecte.
 * Etats geres : chargement (skeleton), erreur (encart), vide, succes.
 */
export function DashboardPage() {
  const [documents, setDocuments] = useState<DocumentItem[] | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let cancelled = false
    setLoading(true)
    setError(null)

    api
      .listDocuments()
      .then((items) => {
        if (!cancelled) setDocuments(items)
      })
      .catch((e) => {
        if (cancelled) return
        if (e instanceof ApiError) {
          setError(e.message)
        } else {
          setError('Erreur lors du chargement des documents')
        }
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })

    return () => {
      cancelled = true
    }
  }, [])

  return (
    <Layout>
      <div className="mb-6">
        <h1 className="text-xl font-semibold text-slate-900">Mes documents</h1>
        <p className="mt-1 text-sm text-slate-500">
          Retrouvez ici les documents en attente de signature et ceux deja traites.
        </p>
      </div>

      {loading && <DocumentListSkeleton />}

      {!loading && error && (
        <ErrorMessage message={error} className="mb-3" />
      )}

      {!loading && !error && documents && documents.length === 0 && (
        <EmptyState
          title="Aucun document disponible"
          description="Aucun document n'a ete depose pour le moment."
        />
      )}

      {!loading && !error && documents && documents.length > 0 && (
        <DocumentList documents={documents} />
      )}
    </Layout>
  )
}
