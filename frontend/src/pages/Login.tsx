import { useState, type FormEvent } from 'react'
import { useNavigate, useLocation } from 'react-router-dom'
import { Loader } from '@/components/Loader'
import { ErrorMessage } from '@/components/ErrorMessage'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useAuth } from '@/hooks/useAuth'
import { ApiError } from '@/services/api'

interface LocationState {
  from?: string
}

/**
 * Page de connexion. Formulaire simple email + mot de passe.
 * Le retour utilisateur est compose d'un loader sur le bouton et d'un encart d'erreur.
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
      // ApiError remonte un message deja traduit en francais
      setError(e instanceof ApiError ? e.message : 'Erreur inattendue')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4">
      <Card className="w-full max-w-sm">
        <CardHeader>
          <CardTitle>Connexion</CardTitle>
          <CardDescription>Acces a votre espace privatif</CardDescription>
        </CardHeader>
        <form onSubmit={handleSubmit} noValidate>
          <CardContent className="space-y-4">
            <div className="space-y-1.5">
              <Label htmlFor="email">Adresse email</Label>
              <Input
                id="email"
                type="email"
                autoComplete="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                disabled={submitting}
                placeholder="prenom.nom@example.test"
                required
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="password">Mot de passe</Label>
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
          </CardContent>
          <CardFooter>
            <Button type="submit" disabled={submitting} className="w-full">
              {submitting ? (
                <>
                  <Loader size={18} /> Connexion...
                </>
              ) : (
                'Se connecter'
              )}
            </Button>
          </CardFooter>
        </form>
      </Card>
    </div>
  )
}
