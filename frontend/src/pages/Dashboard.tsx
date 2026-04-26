import { Layout } from '@/components/Layout'

/**
 * Tableau de bord locataire.
 * Le contenu (liste des documents) sera ajoute dans la feature suivante.
 */
export function DashboardPage() {
  return (
    <Layout>
      <h1 className="text-xl font-semibold text-slate-900">Mes documents</h1>
      <p className="mt-2 text-sm text-slate-500">
        La liste des documents sera affichee ici.
      </p>
    </Layout>
  )
}
