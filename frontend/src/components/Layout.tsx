import { Link, NavLink, useNavigate } from 'react-router-dom'
import { FilePlus2, LogOut, UserCog } from 'lucide-react'
import type { ReactNode } from 'react'
import { Button } from '@/components/ui/button'
import { Footer } from '@/components/Footer'
import { Logo } from '@/components/Logo'
import { ThemeToggle } from '@/components/ThemeToggle'
import { useAuth } from '@/hooks/useAuth'
import { useTheme } from '@/hooks/useTheme'
import { cn } from '@/lib/cn'

interface LayoutProps {
  children: ReactNode
}

/**
 * Mise en page commune apres connexion : header sticky, contenu central
 * dans le container global, footer en bas. Supporte les modes clair / sombre.
 */
export function Layout({ children }: LayoutProps) {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const { resolved } = useTheme()

  async function handleLogout() {
    await logout()
    navigate('/login', { replace: true })
  }

  const isAdmin = user?.role === 'admin'
  const initials = (user?.firstName?.[0] ?? user?.email?.[0] ?? '?').toUpperCase()

  return (
    <div className="flex min-h-screen flex-col bg-sand-50 text-ink dark:bg-brand-900 dark:text-sand-100">
      <header
        className={cn(
          'sticky top-0 z-30 border-b border-sand-200 bg-white/85 backdrop-blur',
          'supports-[backdrop-filter]:bg-white/70',
          'dark:border-brand-700 dark:bg-brand-800/85 dark:supports-[backdrop-filter]:bg-brand-800/70',
        )}
      >
        <div className="container-app flex h-16 items-center justify-between gap-4">
          <Link to="/" className="flex items-center" aria-label="Accueil Espace Privatif">
            {/* Logo : variante claire (texte blanc) en mode sombre */}
            <Logo variant={resolved === 'dark' ? 'light' : 'default'} />
          </Link>

          {user && (
            <nav className="flex items-center gap-1.5 sm:gap-2">
              {isAdmin && (
                <NavItem to="/admin/documents" icon={<FilePlus2 className="h-4 w-4" />} label="Admin" />
              )}
              <NavItem to="/profile" icon={<UserCog className="h-4 w-4" />} label="Profil" />

              <ThemeToggle />

              <div className="mx-1 hidden h-8 w-px bg-sand-200 dark:bg-brand-700 sm:block" />

              <div className="hidden items-center gap-2 pl-1 sm:flex">
                <div
                  className="flex h-8 w-8 items-center justify-center rounded-full bg-brand-500 text-xs font-semibold text-white dark:bg-accent-500 dark:text-brand-900"
                  aria-hidden
                >
                  {initials}
                </div>
                <span className="max-w-[140px] truncate text-sm text-slate-600 dark:text-sand-200">
                  {user.firstName ?? user.email}
                </span>
              </div>

              <Button
                variant="ghost"
                size="sm"
                onClick={handleLogout}
                className={cn(
                  'text-slate-600 hover:bg-danger-50 hover:text-danger-700',
                  'dark:text-sand-200 dark:hover:bg-danger-700 dark:hover:text-white',
                )}
              >
                <LogOut className="h-4 w-4" />
                <span className="hidden sm:inline">Deconnexion</span>
              </Button>
            </nav>
          )}

          {/* Si pas authentifie (cas particulier), on garde quand meme le toggle theme */}
          {!user && (
            <nav className="flex items-center gap-2">
              <ThemeToggle />
            </nav>
          )}
        </div>
      </header>

      <main className="flex-1">
        <div className="container-app py-8 sm:py-10">{children}</div>
      </main>

      <Footer />
    </div>
  )
}

function NavItem({ to, icon, label }: { to: string; icon: ReactNode; label: string }) {
  return (
    <NavLink
      to={to}
      className={({ isActive }) =>
        cn(
          'inline-flex h-9 items-center gap-1.5 rounded-md px-3 text-sm font-medium transition-colors',
          isActive
            ? 'bg-brand-50 text-brand-600 dark:bg-brand-700 dark:text-accent-300'
            : 'text-slate-600 hover:bg-sand-100 hover:text-ink dark:text-sand-200 dark:hover:bg-brand-700 dark:hover:text-white',
        )
      }
    >
      {icon}
      <span className="hidden sm:inline">{label}</span>
    </NavLink>
  )
}
