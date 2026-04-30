import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/button'
import { Card, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { Loader } from '@/components/Loader'
import { ErrorMessage } from '@/components/ErrorMessage'

interface RefuseDialogProps {
  onConfirm: (reason: string) => Promise<void>
  onCancel: () => void
}

/**
 * Boite modale legere : on demande la raison du refus avant validation.
 * Pas de portail / aucune dependance, simple overlay positionne en fixed.
 */
export function RefuseDialog({ onConfirm, onCancel }: RefuseDialogProps) {
  const [reason, setReason] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    if (reason.trim().length < 3) {
      setError('Merci d indiquer la raison du refus (3 caracteres minimum)')
      return
    }
    setSubmitting(true)
    try {
      await onConfirm(reason.trim())
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Refus impossible')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div
      role="dialog"
      aria-modal="true"
      className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 px-4 dark:bg-black/70"
    >
      <Card className="w-full max-w-md">
        <CardHeader>
          <CardTitle>Refuser le document</CardTitle>
        </CardHeader>
        <form onSubmit={handleSubmit}>
          <div className="space-y-3 px-5 pb-5">
            <p className="text-sm text-slate-600 dark:text-sand-200">
              Indiquez la raison de votre refus. Cette information sera transmise a l'administrateur.
            </p>
            <textarea
              className="w-full rounded-md border border-slate-300 bg-white p-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:border-brand-500 dark:border-brand-700 dark:bg-brand-900 dark:text-sand-50"
              rows={4}
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              disabled={submitting}
              placeholder="Erreur dans le document, clauses manquantes, etc."
            />
            {error && <ErrorMessage message={error} />}
          </div>
          <CardFooter>
            <Button
              type="button"
              variant="ghost"
              onClick={onCancel}
              disabled={submitting}
            >
              Annuler
            </Button>
            <Button type="submit" variant="destructive" disabled={submitting}>
              {submitting ? <Loader size={16} /> : 'Confirmer le refus'}
            </Button>
          </CardFooter>
        </form>
      </Card>
    </div>
  )
}
