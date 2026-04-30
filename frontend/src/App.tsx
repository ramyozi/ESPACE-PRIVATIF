import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { AuthProvider } from '@/hooks/useAuth'
import { ProtectedRoute } from '@/components/ProtectedRoute'
import { LoginPage } from '@/pages/Login'
import { DashboardPage } from '@/pages/Dashboard'
import { DocumentDetailPage } from '@/pages/DocumentDetail'
import { SignaturePage } from '@/pages/SignaturePage'
import { ProfilePage } from '@/pages/Profile'
import { AdminDocumentsPage } from '@/pages/AdminDocuments'
import { ForgotPasswordPage } from '@/pages/ForgotPassword'
import { ResetPasswordPage } from '@/pages/ResetPassword'

/**
 * Racine de l'application : routeur, contexte d'authentification et pages.
 *
 * Separation stricte des roles :
 *  - les pages "user only" (dashboard, documents, signature) sont protegees
 *    par requireRole="user" : un admin tombera sur /admin/documents
 *  - les pages admin sont protegees par requireRole="admin" : un user
 *    tombera sur / (qui le redirige sur sa propre liste de documents)
 *  - /profile reste accessible aux deux roles
 */
export default function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          {/* Public */}
          <Route path="/login" element={<LoginPage />} />
          <Route path="/forgot-password" element={<ForgotPasswordPage />} />
          <Route path="/reset-password" element={<ResetPasswordPage />} />

          {/* User only */}
          <Route
            path="/"
            element={
              <ProtectedRoute requireRole="user">
                <DashboardPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/documents/:id"
            element={
              <ProtectedRoute requireRole="user">
                <DocumentDetailPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/documents/:id/sign"
            element={
              <ProtectedRoute requireRole="user">
                <SignaturePage />
              </ProtectedRoute>
            }
          />

          {/* Commun aux deux roles */}
          <Route
            path="/profile"
            element={
              <ProtectedRoute>
                <ProfilePage />
              </ProtectedRoute>
            }
          />

          {/* Admin only */}
          <Route
            path="/admin/documents"
            element={
              <ProtectedRoute requireRole="admin">
                <AdminDocumentsPage />
              </ProtectedRoute>
            }
          />

          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  )
}
