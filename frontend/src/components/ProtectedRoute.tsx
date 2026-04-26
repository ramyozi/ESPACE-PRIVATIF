import { Navigate, useLocation } from 'react-router-dom'
import { Loader } from '@/components/Loader'
import { useAuth } from '@/hooks/useAuth'
import type { ReactNode } from 'react'

interface ProtectedRouteProps {
  children: ReactNode
}

/**
 * Garde de route : redirige vers /login si l'utilisateur n'est pas authentifie.
 * Affiche un loader pendant que la session courante est verifiee.
 */
export function ProtectedRoute({ children }: ProtectedRouteProps) {
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
    return <Navigate to="/login" replace state={{ from: location.pathname }} />
  }

  return <>{children}</>
}
