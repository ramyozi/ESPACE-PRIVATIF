import { Check } from 'lucide-react'
import { cn } from '@/lib/cn'

export type SignatureStep = 'document' | 'signature' | 'otp' | 'done'

interface SignatureStepperProps {
  /** Etape actuellement active (mise en avant en bleu). */
  current: SignatureStep
  /**
   * Indique si la signature a deja ete tracee. Utilise pour cocher l'etape
   * "signature" meme quand on est encore sur "otp".
   */
  signatureDone?: boolean
}

interface StepDef {
  id: SignatureStep
  label: string
  hint: string
}

const STEPS: StepDef[] = [
  { id: 'document', label: 'Document', hint: 'Lire le document' },
  { id: 'signature', label: 'Signature', hint: 'Tracer votre signature' },
  { id: 'otp', label: 'Code', hint: 'Saisir le code email' },
  { id: 'done', label: 'Validation', hint: 'Confirmation finale' },
]

/**
 * Indicateur de progression du parcours de signature.
 *
 * Composant 100% UI : il ne fait aucun appel API, il reflete simplement
 * l'etape courante du parent. Repond aux mediums (md+) en horizontal,
 * et passe en empile sur mobile.
 */
export function SignatureStepper({ current, signatureDone }: SignatureStepperProps) {
  const currentIndex = STEPS.findIndex((s) => s.id === current)

  return (
    <ol
      className="flex flex-col gap-3 md:flex-row md:items-start md:gap-0"
      aria-label="Etapes de la signature"
    >
      {STEPS.map((step, i) => {
        // Une etape est consideree comme cochee si :
        //  - on est sur une etape posterieure
        //  - OU on est sur "otp" et la signature est deja tracee
        const completed =
          i < currentIndex || (step.id === 'signature' && !!signatureDone && current !== 'signature')
        const active = step.id === current

        return (
          <li
            key={step.id}
            className="flex items-start gap-3 md:flex-1 md:flex-col md:items-center md:text-center"
            aria-current={active ? 'step' : undefined}
          >
            <div className="flex items-center md:flex-col md:items-center">
              <div
                className={cn(
                  'flex h-8 w-8 items-center justify-center rounded-full border-2 text-xs font-semibold transition-colors',
                  completed && 'border-blue-600 bg-blue-600 text-white',
                  active && 'border-blue-600 bg-white text-blue-600 shadow-sm',
                  !completed && !active && 'border-slate-300 bg-white text-slate-400',
                )}
              >
                {completed ? <Check className="h-4 w-4" aria-hidden /> : i + 1}
              </div>
              {/* Connecteur horizontal entre etapes (md+) */}
              {i < STEPS.length - 1 && (
                <span
                  className={cn(
                    'mx-2 hidden h-0.5 w-full max-w-[80px] md:block',
                    i < currentIndex ? 'bg-blue-600' : 'bg-slate-200',
                  )}
                  aria-hidden
                />
              )}
            </div>

            <div className="md:mt-2">
              <p
                className={cn(
                  'text-sm font-semibold',
                  active && 'text-blue-700',
                  completed && 'text-slate-900',
                  !active && !completed && 'text-slate-500',
                )}
              >
                {step.label}
              </p>
              <p className="text-xs text-slate-500">{step.hint}</p>
            </div>
          </li>
        )
      })}
    </ol>
  )
}
