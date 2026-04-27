import { useEffect, useRef } from 'react'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { cn } from '@/lib/cn'

interface OtpFormProps {
  value: string
  onChange: (value: string) => void
  disabled?: boolean
  /** Longueur attendue du code (par defaut 6) */
  length?: number
}

/**
 * Champ de saisie OTP a 6 chiffres.
 *
 * Implementation pragmatique : un seul input controle (focus auto, inputMode
 * numeric, pattern strict) pour rester simple et accessible. On ajoute par
 * dessus une visualisation type "6 cases" qui reflete la valeur saisie,
 * sans dupliquer la logique de saisie.
 */
export function OtpForm({ value, onChange, disabled, length = 6 }: OtpFormProps) {
  const inputRef = useRef<HTMLInputElement | null>(null)

  // Focus automatique a l'ouverture pour fluidifier la saisie
  useEffect(() => {
    inputRef.current?.focus()
  }, [])

  function handleChange(raw: string) {
    // On garde uniquement les chiffres et on tronque a la longueur attendue
    const cleaned = raw.replace(/\D/g, '').slice(0, length)
    onChange(cleaned)
  }

  // Index du prochain caractere a saisir, pour mettre en surbrillance la case
  const nextIndex = Math.min(value.length, length - 1)

  return (
    <div className="space-y-3">
      <Label htmlFor="otp" className="text-sm">
        Code recu par email
      </Label>

      {/* Visualisation type "6 cases".
          On clique dessus pour rediriger le focus vers l'input cache. */}
      <button
        type="button"
        onClick={() => inputRef.current?.focus()}
        disabled={disabled}
        className="flex w-full justify-center gap-2 disabled:cursor-not-allowed"
        aria-hidden
        tabIndex={-1}
      >
        {Array.from({ length }).map((_, i) => {
          const filled = i < value.length
          const active = !disabled && i === nextIndex && document.activeElement === inputRef.current
          return (
            <div
              key={i}
              className={cn(
                'flex h-12 w-10 items-center justify-center rounded-md border-2 font-mono text-lg transition-all',
                filled && 'border-blue-500 bg-white text-slate-900 shadow-sm',
                !filled && active && 'border-blue-400 bg-white animate-pulse',
                !filled && !active && 'border-slate-200 bg-slate-50 text-slate-300',
                disabled && 'opacity-50',
              )}
            >
              {value[i] ?? ''}
            </div>
          )
        })}
      </button>

      {/* Input reel : conserve l'a11y native (autocomplete one-time-code,
          inputMode numeric, pattern). On le rend visuellement discret
          pour ne pas doubler l'affichage avec les 6 cases au-dessus. */}
      <Input
        ref={inputRef}
        id="otp"
        name="otp"
        type="text"
        autoComplete="one-time-code"
        inputMode="numeric"
        pattern="\d*"
        maxLength={length}
        value={value}
        onChange={(e) => handleChange(e.target.value)}
        disabled={disabled}
        placeholder="123456"
        className="tracking-[0.4em] text-center font-mono"
      />

      <p className="text-xs text-slate-500">
        Le code a 6 chiffres a ete envoye a votre adresse email. Il est valable 5 minutes.
      </p>
    </div>
  )
}
