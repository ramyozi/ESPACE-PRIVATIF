import { useEffect, useState, type FormEvent } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { ArrowLeft, KeyRound, ShieldCheck } from 'lucide-react'
import { Loader } from '@/components/Loader'
import { ErrorMessage } from '@/components/ErrorMessage'
import { SuccessMessage } from '@/components/SuccessMessage'
import { Logo } from '@/components/Logo'
import { ThemeToggle } from '@/components/ThemeToggle'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { ApiError, api } from '@/services/api'

/**
 * Page de reset apres clic sur le lien recu par mail.
 * Le token est lu depuis ?token=...
 *
 *  - token absent / invalide -> message d'erreur clair
 *  - succes -> redirige automatiquement vers /login apres 2 secondes
 */
export function ResetPasswordPage() {
  const [params] = useSearchParams()
  const token = params.get('token') ?? ''
  const navigate = useNavigate()

  const [password, setPassword] = useState('')
  const [confirm, setConfirm] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  // Si pas de token dans l'URL, on l'affiche tout de suite (UX claire).
  useEffect(() => {
    if (!token) setError('Lien invalide ou expire')
  }, [token])

  // Apres succes, redirection automatique vers le login.
  useEffect(() => {
    if (!success) return
    const t = window.setTimeout(() => navigate('/login', { replace: true }), 2000)
    return () => window.clearTimeout(t)
  }, [success, navigate])

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setSuccess(null)

    if (!token) {
      setError('Lien invalide')
      return
    }
    if (password.length < 8) {
      setError('Le mot de passe doit faire au moins 8 caracteres')
      return
    }
    if (password !== confirm) {
      setError('Les deux mots de passe ne correspondent pas')
      return
    }

    setSubmitting(true)
    try {
      await api.resetPassword(token, password)
      setSuccess('Mot de passe reinitialise. Redirection vers la connexion...')
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Reinitialisation impossible')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="relative flex min-h-screen items-center justify-center bg-sand-50 px-4 dark:bg-brand-900">
      <div className="absolute right-4 top-4">
        <ThemeToggle />
      </div>

      <div className="w-full max-w-sm">
        <div className="mb-8 flex justify-center">
          <Logo size={36} />
        </div>

        <h2 className="font-display text-2xl font-bold text-ink dark:text-sand-50">
          Nouveau mot de passe
        </h2>
        <p className="mt-1 text-sm text-slate-500 dark:text-sand-200">
          Choisissez un mot de passe d'au moins 8 caracteres.
        </p>

        <form onSubmit={handleSubmit} className="mt-6 space-y-5" noValidate>
          <div className="space-y-1.5">
            <Label htmlFor="password" className="flex items-center gap-2">
              <KeyRound className="h-4 w-4" /> Nouveau mot de passe
            </Label>
            <Input
              id="password"
              type="password"
              autoComplete="new-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              disabled={submitting || !token}
              minLength={8}
              required
              autoFocus
            />
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="confirm" className="flex items-center gap-2">
              <ShieldCheck className="h-4 w-4" /> Confirmer
            </Label>
            <Input
              id="confirm"
              type="password"
              autoComplete="new-password"
              value={confirm}
              onChange={(e) => setConfirm(e.target.value)}
              disabled={submitting || !token}
              minLength={8}
              required
            />
          </div>

          {error && <ErrorMessage message={error} />}
          {success && <SuccessMessage message={success} />}

          <Button
            type="submit"
            disabled={submitting || !token}
            className="w-full bg-brand-500 text-white shadow-card hover:bg-brand-600 dark:bg-accent-500 dark:text-brand-900 dark:hover:bg-accent-400"
          >
            {submitting ? (
              <>
                <Loader size={16} /> Validation...
              </>
            ) : (
              'Reinitialiser'
            )}
          </Button>

          <Link
            to="/login"
            className="flex items-center justify-center gap-1.5 text-xs text-brand-500 hover:underline dark:text-accent-300"
          >
            <ArrowLeft className="h-3 w-3" /> Retour a la connexion
          </Link>
        </form>
      </div>
    </div>
  )
}
