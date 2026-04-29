import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { ArrowLeft, KeyRound, Mail, Save } from 'lucide-react'
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

/**
 * Page Profil utilisateur.
 * Permet de modifier l'email et/ou le mot de passe.
 * Le mot de passe actuel est requis pour valider toute modification.
 */
export function ProfilePage() {
  const navigate = useNavigate()
  const { user, refresh } = useAuth()

  const [email, setEmail] = useState(user?.email ?? '')
  const [currentPassword, setCurrentPassword] = useState('')
  const [newPassword, setNewPassword] = useState('')
  const [confirmPassword, setConfirmPassword] = useState('')

  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    setSuccess(null)

    if (!currentPassword) {
      setError('Mot de passe actuel requis')
      return
    }
    if (newPassword && newPassword !== confirmPassword) {
      setError('Les deux nouveaux mots de passe ne correspondent pas')
      return
    }

    const emailChanged = !!user && email.trim().toLowerCase() !== user.email.toLowerCase()
    const wantsPasswordChange = newPassword.length > 0
    if (!emailChanged && !wantsPasswordChange) {
      setError('Aucune modification a appliquer')
      return
    }

    setSubmitting(true)
    try {
      const result = await api.updateProfile({
        currentPassword,
        email: emailChanged ? email.trim() : undefined,
        newPassword: wantsPasswordChange ? newPassword : undefined,
      })

      const parts: string[] = []
      if (result.updated.email) parts.push('Email mis a jour')
      if (result.updated.password) parts.push('Mot de passe mis a jour')
      setSuccess(parts.join(' + '))

      // Rafraichit le user en session pour refleter le nouvel email partout
      await refresh()

      setCurrentPassword('')
      setNewPassword('')
      setConfirmPassword('')
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Modification impossible')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Layout>
      <div className="mb-4">
        <Button variant="ghost" size="sm" onClick={() => navigate('/')}>
          <ArrowLeft className="h-4 w-4" /> Retour aux documents
        </Button>
      </div>

      <Card className="mx-auto max-w-xl">
        <CardHeader>
          <CardTitle>Mon profil</CardTitle>
          <CardDescription>
            Modifiez votre email et votre mot de passe. Le mot de passe actuel
            est requis pour confirmer votre identite.
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form className="space-y-5" onSubmit={handleSubmit}>
            <div className="space-y-1.5">
              <Label htmlFor="email" className="flex items-center gap-2">
                <Mail className="h-4 w-4" /> Email
              </Label>
              <Input
                id="email"
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                disabled={submitting}
                required
              />
            </div>

            <div className="border-t border-slate-100 pt-4 space-y-1.5">
              <Label htmlFor="currentPassword" className="flex items-center gap-2">
                <KeyRound className="h-4 w-4" /> Mot de passe actuel
              </Label>
              <Input
                id="currentPassword"
                type="password"
                value={currentPassword}
                onChange={(e) => setCurrentPassword(e.target.value)}
                disabled={submitting}
                autoComplete="current-password"
                required
              />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="newPassword">Nouveau mot de passe (optionnel)</Label>
              <Input
                id="newPassword"
                type="password"
                value={newPassword}
                onChange={(e) => setNewPassword(e.target.value)}
                disabled={submitting}
                placeholder="Laissez vide pour ne pas changer"
                autoComplete="new-password"
                minLength={8}
              />
              <p className="text-xs text-slate-500">8 caracteres minimum.</p>
            </div>

            {newPassword.length > 0 && (
              <div className="space-y-1.5">
                <Label htmlFor="confirmPassword">Confirmer le nouveau mot de passe</Label>
                <Input
                  id="confirmPassword"
                  type="password"
                  value={confirmPassword}
                  onChange={(e) => setConfirmPassword(e.target.value)}
                  disabled={submitting}
                  autoComplete="new-password"
                />
              </div>
            )}

            {error && <ErrorMessage message={error} />}
            {success && <SuccessMessage message={success} />}

            <div className="flex justify-end">
              <Button type="submit" disabled={submitting}>
                {submitting ? (
                  <>
                    <Loader size={16} /> Enregistrement...
                  </>
                ) : (
                  <>
                    <Save className="h-4 w-4" /> Enregistrer
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
