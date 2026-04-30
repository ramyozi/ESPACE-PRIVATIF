import { useState, type FormEvent } from 'react'
import { useNavigate, useLocation } from 'react-router-dom'
import { ShieldCheck, Sparkles } from 'lucide-react'
import { Loader } from '@/components/Loader'
import { ErrorMessage } from '@/components/ErrorMessage'
import { Logo } from '@/components/Logo'
import { ThemeToggle } from '@/components/ThemeToggle'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useAuth } from '@/hooks/useAuth'
import { ApiError } from '@/services/api'

interface LocationState {
  from?: string
}

/**
 * Page de connexion : split-screen plein ecran.
 *  - colonne gauche (cachee en mobile) : branding + valeurs produit
 *  - colonne droite : formulaire centre, focus immediat sur email
 */
export function LoginPage() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const from = (location.state as LocationState | null)?.from ?? '/'

  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)

    if (!email || !password) {
      setError('Veuillez renseigner votre email et votre mot de passe')
      return
    }

    setSubmitting(true)
    try {
      await login(email.trim(), password)
      navigate(from, { replace: true })
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Erreur inattendue')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="relative grid min-h-screen lg:grid-cols-2">
      {/* Toggle theme accessible avant meme la connexion */}
      <div className="absolute right-4 top-4 z-10">
        <ThemeToggle />
      </div>
      {/* --- Colonne branding (desktop only) --- */}
      <aside className="relative hidden overflow-hidden bg-hero-gradient lg:flex lg:flex-col lg:justify-between lg:p-12 lg:text-white">
        {/* Motif decoratif discret */}
        <div
          className="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-accent-400/10 blur-3xl"
          aria-hidden
        />
        <div
          className="pointer-events-none absolute -bottom-32 -left-16 h-96 w-96 rounded-full bg-white/5 blur-3xl"
          aria-hidden
        />

        <Logo variant="light" size={36} />

        <div className="relative max-w-md">
          <h1 className="font-display text-4xl font-bold leading-tight">
            Signez vos documents en toute serenite.
          </h1>
          <p className="mt-4 text-base leading-relaxed text-white/80">
            Espace Privatif vous accompagne pour gerer vos documents
            locatifs en quelques clics, depuis n'importe quel appareil.
          </p>

          <ul className="mt-8 space-y-3 text-sm text-white/90">
            <li className="flex items-start gap-3">
              <ShieldCheck className="mt-0.5 h-5 w-5 text-accent-400" aria-hidden />
              <span>Signature electronique securisee avec verification par email</span>
            </li>
            <li className="flex items-start gap-3">
              <Sparkles className="mt-0.5 h-5 w-5 text-accent-400" aria-hidden />
              <span>Acces immediat a vos documents et leur historique</span>
            </li>
          </ul>
        </div>

        <p className="relative text-xs text-white/60">
          &copy; {new Date().getFullYear()} Realsoft Immobilier
        </p>
      </aside>

      {/* --- Colonne formulaire --- */}
      <section className="flex flex-col justify-center bg-sand-50 px-6 py-12 dark:bg-brand-900 sm:px-12">
        <div className="mx-auto w-full max-w-sm">
          <div className="mb-8 lg:hidden">
            <Logo size={36} />
          </div>

          <h2 className="font-display text-2xl font-bold text-ink dark:text-sand-50">
            Bon retour parmi nous.
          </h2>
          <p className="mt-1 text-sm text-slate-500 dark:text-sand-200">
            Connectez-vous a votre espace privatif.
          </p>

          <form onSubmit={handleSubmit} noValidate className="mt-8 space-y-5">
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
            <div className="space-y-1.5">
              <div className="flex items-center justify-between">
                <Label htmlFor="password">Mot de passe</Label>
                <a
                  href="#"
                  className="text-xs text-brand-500 hover:underline dark:text-accent-300"
                >
                  Mot de passe oublie ?
                </a>
              </div>
              <Input
                id="password"
                type="password"
                autoComplete="current-password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                disabled={submitting}
                required
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
                  <Loader size={18} /> Connexion...
                </>
              ) : (
                'Se connecter'
              )}
            </Button>

            <p className="pt-2 text-center text-xs text-slate-500 dark:text-sand-300">
              En continuant, vous acceptez nos conditions d'utilisation
              et notre politique de confidentialite.
            </p>
          </form>
        </div>
      </section>
    </div>
  )
}
