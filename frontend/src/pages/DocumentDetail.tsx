import { useCallback, useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { ArrowLeft, FileSignature, XCircle } from 'lucide-react'
import { Layout } from '@/components/Layout'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Loader } from '@/components/Loader'
import { ErrorMessage } from '@/components/ErrorMessage'
import { SuccessMessage } from '@/components/SuccessMessage'
import { StateBadge } from '@/components/StateBadge'
import { RefuseDialog } from '@/components/RefuseDialog'
import { DocumentDetailSkeleton } from '@/components/DocumentDetailSkeleton'
import { formatDate } from '@/lib/documentState'
import { ApiError, api, type DocumentItem } from '@/services/api'

/**
 * Page de detail d'un document.
 *
 * Charge le document, expose les actions :
 *  - Signer (redirige vers /documents/:id/sign, ajoute dans la feature signature)
 *  - Refuser (modale + confirmation)
 *  - Telecharger le PDF signe (si etat signe_valide)
 */
export function DocumentDetailPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const documentId = Number(id)

  const [document, setDocument] = useState<DocumentItem | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [showRefuseDialog, setShowRefuseDialog] = useState(false)
  const [actionLoading, setActionLoading] = useState(false)

  const loadDocument = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const data = await api.getDocument(documentId)
      setDocument(data)
    } catch (e) {
      setError(
        e instanceof ApiError ? e.message : 'Erreur lors du chargement du document',
      )
    } finally {
      setLoading(false)
    }
  }, [documentId])

  useEffect(() => {
    if (Number.isNaN(documentId)) {
      setError('Document invalide')
      setLoading(false)
      return
    }
    loadDocument()
  }, [documentId, loadDocument])

  /**
   * Demarre le parcours de signature : on appelle /sign/start (qui envoie l OTP)
   * puis on redirige vers la page dediee a la capture + saisie OTP.
   */
  async function handleStartSignature() {
    if (!document) return
    setActionLoading(true)
    setError(null)
    setSuccess(null)
    try {
      await api.startSignature(document.id)
      navigate(`/documents/${document.id}/sign`)
    } catch (e) {
      setError(
        e instanceof ApiError ? e.message : 'Impossible de demarrer la signature',
      )
    } finally {
      setActionLoading(false)
    }
  }

  async function handleConfirmRefuse(reason: string) {
    if (!document) return
    try {
      await api.refuseDocument(document.id, reason)
      setShowRefuseDialog(false)
      setSuccess('Refus enregistre, le administrateur a ete notifie.')
      await loadDocument()
    } catch (e) {
      // Le dialog gere son propre encart d'erreur en relancant l'exception
      throw e instanceof Error ? e : new Error('Refus impossible')
    }
  }

  return (
    <Layout>
      <div className="mb-4">
        <Button variant="ghost" size="sm" onClick={() => navigate('/')}>
          <ArrowLeft className="h-4 w-4" /> Retour aux documents
        </Button>
      </div>

      {loading && <DocumentDetailSkeleton />}

      {!loading && error && <ErrorMessage message={error} />}

      {!loading && !error && document && (
        <Card>
          <CardHeader>
            <div className="flex items-start justify-between gap-3">
              <div>
                <CardTitle>{document.title}</CardTitle>
                <CardDescription>
                  Reference {document.sothisDocumentId}
                  {' · '}
                  Type {document.type}
                </CardDescription>
              </div>
              <StateBadge state={document.state} />
            </div>
          </CardHeader>

          <CardContent className="space-y-3 text-sm text-slate-700 dark:text-sand-100">
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
              <DetailRow label="Cree le" value={formatDate(document.createdAt)} />
              <DetailRow
                label="Date limite"
                value={document.deadline ? formatDate(document.deadline) : 'Non definie'}
              />
              <DetailRow label="Mis a jour le" value={formatDate(document.updatedAt)} />
              <DetailRow
                label="PDF signe"
                value={document.hasSignedPdf ? 'Disponible' : 'Pas encore disponible'}
              />
            </div>

            {success && <SuccessMessage message={success} className="mt-4" />}
          </CardContent>

          <CardFooter className="flex flex-wrap items-center justify-end gap-2">
            {document.state === 'en_attente_signature' && (
              <>
                <Button
                  variant="ghost"
                  onClick={() => setShowRefuseDialog(true)}
                  disabled={actionLoading}
                >
                  <XCircle className="h-4 w-4" /> Refuser
                </Button>
                <Button onClick={handleStartSignature} disabled={actionLoading}>
                  {actionLoading ? (
                    <>
                      <Loader size={16} /> Preparation...
                    </>
                  ) : (
                    <>
                      <FileSignature className="h-4 w-4" /> Signer le document
                    </>
                  )}
                </Button>
              </>
            )}

            {document.state === 'signature_en_cours' && (
              <Button onClick={() => navigate(`/documents/${document.id}/sign`)}>
                <FileSignature className="h-4 w-4" /> Reprendre la signature
              </Button>
            )}

            {document.state === 'signe' && (
              <p className="text-sm text-slate-500 dark:text-sand-200">
                En attente de validation par le administrateur.
              </p>
            )}

            {document.state === 'signe_valide' && document.hasSignedPdf && (
              <p className="text-sm text-emerald-700 dark:text-emerald-300">
                Le document signe est disponible cote administrateur.
              </p>
            )}
          </CardFooter>
        </Card>
      )}

      {showRefuseDialog && (
        <RefuseDialog
          onCancel={() => setShowRefuseDialog(false)}
          onConfirm={handleConfirmRefuse}
        />
      )}
    </Layout>
  )
}

interface DetailRowProps {
  label: string
  value: string
}

function DetailRow({ label, value }: DetailRowProps) {
  return (
    <div>
      <p className="text-xs uppercase tracking-wide text-slate-400 dark:text-sand-300">{label}</p>
      <p className="text-sm font-medium text-slate-900 dark:text-sand-50">{value}</p>
    </div>
  )
}
