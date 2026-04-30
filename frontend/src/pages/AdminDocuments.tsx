import { useEffect, useState, type FormEvent } from 'react'
import { FileText, Send, Upload } from 'lucide-react'
import { Layout } from '@/components/Layout'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Loader } from '@/components/Loader'
import { ErrorMessage } from '@/components/ErrorMessage'
import { SuccessMessage } from '@/components/SuccessMessage'
import { useAuth } from '@/hooks/useAuth'
import { ApiError, api } from '@/services/api'

interface AdminUser {
  id: number
  email: string
  firstName: string | null
  lastName: string | null
}

/**
 * Page Admin : creation et depot d'un document a signer pour un utilisateur.
 *
 * Reutilise le flow existant : le document cree apparait immediatement chez
 * le destinataire en etat "en_attente_signature" et est signable via /sign/start
 * + OTP + /sign/complete (aucune logique nouvelle cote signature).
 */
export function AdminDocumentsPage() {
  const { user } = useAuth()

  const [users, setUsers] = useState<AdminUser[]>([])
  const [loadingUsers, setLoadingUsers] = useState(true)

  const [userId, setUserId] = useState<string>('')
  const [title, setTitle] = useState('')
  const [type, setType] = useState('')
  const [deadline, setDeadline] = useState('')
  const [file, setFile] = useState<File | null>(null)

  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  useEffect(() => {
    let cancelled = false
    api
      .adminListUsers()
      .then((items) => {
        if (!cancelled) setUsers(items)
      })
      .catch((e) => {
        if (!cancelled) setError(e instanceof ApiError ? e.message : 'Chargement impossible')
      })
      .finally(() => {
        if (!cancelled) setLoadingUsers(false)
      })
    return () => {
      cancelled = true
    }
  }, [])

  function resetForm() {
    setUserId('')
    setTitle('')
    setType('')
    setDeadline('')
    setFile(null)
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    setSuccess(null)

    const targetId = Number(userId)
    if (!targetId) {
      setError('Selectionnez un destinataire')
      return
    }
    if (!title.trim()) {
      setError('Titre requis')
      return
    }
    if (!file) {
      setError('Fichier PDF requis')
      return
    }
    if (file.type !== 'application/pdf') {
      setError('Seuls les PDF sont acceptes')
      return
    }

    setSubmitting(true)
    try {
      const result = await api.adminUploadDocument({
        file,
        userId: targetId,
        documentName: title.trim(),
        type: type.trim() || undefined,
        // deadline : input type=datetime-local renvoie "YYYY-MM-DDTHH:mm" sans TZ.
        // On laisse le backend interpreter via DateTimeImmutable.
        deadline: deadline || undefined,
      })
      setSuccess(`Document #${result.documentId} cree (etat : ${result.state})`)
      resetForm()
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Creation impossible')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Layout>
      <header className="mb-8">
        <p className="text-sm font-medium uppercase tracking-wider text-accent-500 dark:text-accent-300">
          Espace administrateur
        </p>
        <h1 className="mt-1 font-display text-2xl font-bold text-ink dark:text-sand-50 sm:text-3xl">
          Deposer un document a signer
        </h1>
        <p className="mt-1 text-sm text-slate-500 dark:text-sand-200">
          Le document apparaitra immediatement dans l'espace du destinataire choisi.
        </p>
      </header>

      <Card className="mx-auto max-w-2xl border-sand-200 bg-white shadow-card dark:border-brand-700 dark:bg-brand-800">
        <CardHeader className="border-b border-sand-100 dark:border-brand-700">
          <CardTitle className="flex items-center gap-2 font-display text-lg">
            <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-50 text-brand-500 dark:bg-brand-700 dark:text-accent-300">
              <FileText className="h-4 w-4" />
            </span>
            Nouveau document
          </CardTitle>
          <CardDescription>
            Tenant : <span className="font-mono text-brand-500 dark:text-accent-300">{user?.tenantId ?? '-'}</span>
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form className="space-y-5" onSubmit={handleSubmit}>
            <div className="space-y-1.5">
              <Label htmlFor="user">Destinataire</Label>
              {loadingUsers ? (
                <div className="py-2 text-sm text-slate-500 dark:text-sand-200">
                  <Loader size={14} /> Chargement des utilisateurs...
                </div>
              ) : (
                <select
                  id="user"
                  className="flex h-10 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-brand-700 dark:bg-brand-800 dark:text-sand-50"
                  value={userId}
                  onChange={(e) => setUserId(e.target.value)}
                  disabled={submitting}
                  required
                >
                  <option value="">-- Selectionner --</option>
                  {users.map((u) => {
                    const name = [u.firstName, u.lastName].filter(Boolean).join(' ').trim()
                    return (
                      <option key={u.id} value={u.id}>
                        {name ? `${name} (${u.email})` : u.email}
                      </option>
                    )
                  })}
                </select>
              )}
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="title">Titre du document</Label>
              <Input
                id="title"
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                disabled={submitting}
                placeholder="Contrat, autorisation, attestation..."
                required
                maxLength={200}
              />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-1.5">
                <Label htmlFor="type">Type (optionnel)</Label>
                <Input
                  id="type"
                  value={type}
                  onChange={(e) => setType(e.target.value)}
                  disabled={submitting}
                  placeholder="contrat, attestation, autorisation..."
                  maxLength={40}
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="deadline">Date limite (optionnelle)</Label>
                <Input
                  id="deadline"
                  type="datetime-local"
                  value={deadline}
                  onChange={(e) => setDeadline(e.target.value)}
                  disabled={submitting}
                />
              </div>
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="file" className="flex items-center gap-2">
                <Upload className="h-4 w-4" /> Fichier PDF (10 Mo max)
              </Label>
              <Input
                id="file"
                type="file"
                accept="application/pdf"
                onChange={(e) => setFile(e.target.files?.[0] ?? null)}
                disabled={submitting}
                required
              />
              {file && (
                <p className="text-xs text-slate-500">
                  {file.name} ({Math.round(file.size / 1024)} Ko)
                </p>
              )}
            </div>

            {error && <ErrorMessage message={error} />}
            {success && <SuccessMessage message={success} />}

            <div className="flex justify-end">
              <Button type="submit" disabled={submitting}>
                {submitting ? (
                  <>
                    <Loader size={16} /> Envoi...
                  </>
                ) : (
                  <>
                    <Send className="h-4 w-4" /> Creer le document
                  </>
                )}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </Layout>
  )
}
