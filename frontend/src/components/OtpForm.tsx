import { useEffect, useRef } from 'react'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

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
 * Implementation pragmatique : un seul input avec inputMode numeric,
 * pattern strict et focus auto. Pas de "6 inputs separes" pour rester
 * simple et accessible.
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

  return (
    <div className="space-y-1.5">
      <Label htmlFor="otp">Code recu par email</Label>
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
        className="tracking-[0.4em] text-center font-mono text-lg"
      />
      <p className="text-xs text-slate-500">
        Le code a ete envoye a votre adresse email.
        Il est valable 5 minutes.
      </p>
    </div>
  )
}
