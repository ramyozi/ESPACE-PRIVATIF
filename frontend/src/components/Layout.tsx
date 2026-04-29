import { Link, useNavigate } from 'react-router-dom'
import { FilePlus2, LogOut, UserCog } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useAuth } from '@/hooks/useAuth'
import type { ReactNode } from 'react'

interface LayoutProps {
  children: ReactNode
}

/**
 * Mise en page commune apres connexion : barre superieure + contenu centre.
 * Le bouton "Admin" n'apparait que pour les utilisateurs ayant le role admin.
 */
export function Layout({ children }: LayoutProps) {
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  async function handleLogout() {
    await logout()
    navigate('/login', { replace: true })
  }

  const isAdmin = user?.role === 'admin'

  return (
    <div className="min-h-screen bg-slate-50">
      <header className="border-b border-slate-200 bg-white">
        <div className="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
          <Link to="/" className="text-base font-semibold text-slate-900">
            Espace Privatif
          </Link>
          {user && (
            <div className="flex items-center gap-2 text-sm text-slate-600">
              <span className="hidden sm:inline">
                {user.firstName ?? user.email}
              </span>
              {isAdmin && (
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => navigate('/admin/documents')}
                >
                  <FilePlus2 className="h-4 w-4" />
                  <span className="hidden sm:inline">Admin</span>
                </Button>
              )}
              <Button variant="ghost" size="sm" onClick={() => navigate('/profile')}>
                <UserCog className="h-4 w-4" />
                <span className="hidden sm:inline">Mon profil</span>
              </Button>
              <Button variant="ghost" size="sm" onClick={handleLogout}>
                <LogOut className="h-4 w-4" />
                <span className="hidden sm:inline">Se deconnecter</span>
              </Button>
            </div>
          )}
        </div>
      </header>
      <main className="mx-auto max-w-5xl px-4 py-8">{children}</main>
    </div>
  )
}
