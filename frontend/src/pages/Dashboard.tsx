import { useEffect, useState } from 'react'
import { Navigate } from 'react-router-dom'
import { Layout } from '@/components/Layout'
import { DocumentList, DocumentListSkeleton } from '@/components/DocumentList'
import { EmptyState } from '@/components/EmptyState'
import { ErrorMessage } from '@/components/ErrorMessage'
import { useAuth } from '@/hooks/useAuth'
import { ApiError, api, type DocumentItem } from '@/services/api'

/**
 * Tableau de bord : liste des documents du locataire connecte.
 *
 * Cas particulier : un admin n'a pas vocation a voir sa propre liste de
 * documents (il en cree pour les locataires). On le renvoie directement vers
 * la page admin pour eviter toute confusion d'UX.
 */
export function DashboardPage() {
  const { user } = useAuth()
  const [documents, setDocuments] = useState<DocumentItem[] | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)

  const isAdmin = user?.role === 'admin'

  useEffect(() => {
    if (isAdmin) {
      // L'admin sera redirige vers /admin/documents : pas de fetch inutile.
      return
    }
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
  }, [isAdmin])

  if (isAdmin) {
    return <Navigate to="/admin/documents" replace />
  }

  const greeting = user?.firstName ? `Bonjour ${user.firstName},` : 'Bonjour,'
  const pendingCount = documents?.filter((d) => d.state === 'en_attente_signature').length ?? 0

  return (
    <Layout>
      <header className="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-sm font-medium uppercase tracking-wider text-accent-500 dark:text-accent-300">
            Espace Privatif
          </p>
          <h1 className="mt-1 font-display text-2xl font-bold text-ink dark:text-sand-50 sm:text-3xl">
            {greeting}
          </h1>
          <p className="mt-1 text-sm text-slate-500 dark:text-sand-200">
            Retrouvez ici vos documents en attente et leur historique.
          </p>
        </div>
        {!loading && !error && documents && pendingCount > 0 && (
          <div className="rounded-full bg-accent-100 px-4 py-1.5 text-sm font-semibold text-accent-700 dark:bg-accent-500/15 dark:text-accent-300">
            {pendingCount} document{pendingCount > 1 ? 's' : ''} en attente
          </div>
        )}
      </header>

      {loading && <DocumentListSkeleton />}

      {!loading && error && <ErrorMessage message={error} className="mb-3" />}

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
