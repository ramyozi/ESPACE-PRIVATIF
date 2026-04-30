import { Navigate, useLocation } from 'react-router-dom'
import { Loader } from '@/components/Loader'
import { useAuth } from '@/hooks/useAuth'
import type { ReactNode } from 'react'

type RequireRole = 'admin' | 'user'

interface ProtectedRouteProps {
  children: ReactNode
  /**
   * Limite l'acces a un role precis :
   *  - "admin" : seuls les admins peuvent entrer (sinon redirect vers /)
   *  - "user"  : seuls les non-admins peuvent entrer (un admin est renvoye
   *               vers son espace dedie /admin/documents)
   *  Sans valeur : tout utilisateur authentifie peut entrer.
   */
  requireRole?: RequireRole
}

/**
 * Garde de route. Trois cas :
 *  - non authentifie  -> /login avec un message "Session expiree"
 *  - role insuffisant -> redirige vers la landing du role courant
 *  - sinon            -> rend les enfants
 *
 * Affiche un loader pendant que la session courante est verifiee.
 */
export function ProtectedRoute({ children, requireRole }: ProtectedRouteProps) {
  const { user, initializing } = useAuth()
  const location = useLocation()

  if (initializing) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <Loader size={32} />
      </div>
    )
  }

  if (!user) {
    return (
      <Navigate
        to="/login"
        replace
        state={{
          from: location.pathname,
          message: 'Votre session a expire. Veuillez vous reconnecter.',
        }}
      />
    )
  }

  // Verification de role : on redirige vers la landing adaptee si l'utilisateur
  // tape une URL d'une zone qui ne lui est pas destinee.
  if (requireRole === 'admin' && user.role !== 'admin') {
    return <Navigate to="/" replace />
  }
  if (requireRole === 'user' && user.role === 'admin') {
    return <Navigate to="/admin/documents" replace />
  }

  return <>{children}</>
}
