import { useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { ArrowLeft, MailCheck } from 'lucide-react'
import { Loader } from '@/components/Loader'
import { ErrorMessage } from '@/components/ErrorMessage'
import { Logo } from '@/components/Logo'
import { ThemeToggle } from '@/components/ThemeToggle'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { ApiError, api } from '@/services/api'

/**
 * Page "Mot de passe oublie".
 *
 * On envoie l'email au backend qui se charge :
 *  - de generer un token aleatoire
 *  - de l'envoyer par mail
 *  - de repondre TOUJOURS 200 (anti-enumeration)
 *
 * Cote UX : on affiche systematiquement le meme message de succes,
 * meme si l'email n'existe pas.
 */
export function ForgotPasswordPage() {
  const [email, setEmail] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [submitted, setSubmitted] = useState(false)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)

    if (!email || !email.includes('@')) {
      setError('Email invalide')
      return
    }

    setSubmitting(true)
    try {
      await api.forgotPassword(email.trim())
      setSubmitted(true)
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Erreur inattendue')
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

        {submitted ? (
          <div className="rounded-lg border border-success-500/30 bg-success-50 p-6 text-center dark:border-emerald-500/30 dark:bg-emerald-500/10">
            <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-success-500/20 text-success-700 dark:text-emerald-300">
              <MailCheck className="h-6 w-6" aria-hidden />
            </div>
            <h2 className="font-display text-lg font-semibold text-ink dark:text-sand-50">
              Verifiez votre boite mail
            </h2>
            <p className="mt-2 text-sm text-slate-600 dark:text-sand-200">
              Si cette adresse correspond a un compte, vous allez recevoir
              un email avec un lien pour reinitialiser votre mot de passe.
              Le lien est valable 30 minutes.
            </p>
            <Link
              to="/login"
              className="mt-5 inline-flex h-10 items-center justify-center rounded-md bg-brand-500 px-4 text-sm font-medium text-white transition-colors hover:bg-brand-600 dark:bg-accent-500 dark:text-brand-900 dark:hover:bg-accent-400"
            >
              Retour a la connexion
            </Link>
          </div>
        ) : (
          <>
            <h2 className="font-display text-2xl font-bold text-ink dark:text-sand-50">
              Mot de passe oublie ?
            </h2>
            <p className="mt-1 text-sm text-slate-500 dark:text-sand-200">
              Entrez votre email, nous vous enverrons un lien pour
              reinitialiser votre mot de passe.
            </p>

            <form onSubmit={handleSubmit} className="mt-6 space-y-5" noValidate>
              <div className="space-y-1.5">
                <Label htmlFor="email">Adresse email</Label>
                <Input
                  id="email"
                  type="email"
                  autoComplete="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  disabled={submitting}
                  placeholder="prenom.nom@example.fr"
                  required
                  autoFocus
                />
              </div>

              {error && <ErrorMessage message={error} />}

              <Button
                type="submit"
                disabled={submitting}
                className="w-full bg-brand-500 text-white shadow-card hover:bg-brand-600 dark:bg-accent-500 dark:text-brand-900 dark:hover:bg-accent-400"
              >
                {submitting ? (
                  <>
                    <Loader size={16} /> Envoi...
                  </>
                ) : (
                  'Envoyer le lien'
                )}
              </Button>

              <Link
                to="/login"
                className="flex items-center justify-center gap-1.5 text-xs text-brand-500 hover:underline dark:text-accent-300"
              >
                <ArrowLeft className="h-3 w-3" /> Retour a la connexion
              </Link>
            </form>
          </>
        )}
      </div>
    </div>
  )
}
