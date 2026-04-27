import { forwardRef, useEffect, useImperativeHandle, useRef } from 'react'
import SignaturePadLib from 'signature_pad'
import { cn } from '@/lib/cn'

export interface SignaturePadHandle {
  /** Renvoie l'image de signature au format data URL PNG, ou null si vide */
  toDataURL(): string | null
  /** Reinitialise le canvas */
  clear(): void
  /** Vrai si l'utilisateur n'a pas encore trace de signature */
  isEmpty(): boolean
}

interface SignaturePadProps {
  className?: string
  onChange?: (isEmpty: boolean) => void
}

/**
 * Wrapper React autour de signature_pad.
 *
 * Particularites :
 *  - on redimensionne le canvas en fonction du DPR pour rester net en HiDPI
 *  - on expose une API imperative (toDataURL/clear/isEmpty) via une ref
 *  - on remonte un evenement onChange pour activer/desactiver le bouton de validation
 */
export const SignaturePad = forwardRef<SignaturePadHandle, SignaturePadProps>(
  ({ className, onChange }, ref) => {
    const canvasRef = useRef<HTMLCanvasElement | null>(null)
    const padRef = useRef<SignaturePadLib | null>(null)
    const onChangeRef = useRef(onChange)

    useEffect(() => {
      onChangeRef.current = onChange
    }, [onChange])

    useEffect(() => {
      const canvas = canvasRef.current
      if (!canvas) return

      // Mise a la taille reelle du canvas en tenant compte du device pixel ratio
      const resize = () => {
        const ratio = Math.max(window.devicePixelRatio || 1, 1)
        const { width, height } = canvas.getBoundingClientRect()
        canvas.width = width * ratio
        canvas.height = height * ratio
        canvas.getContext('2d')?.scale(ratio, ratio)
        // signature_pad recommande de clear apres un resize
        padRef.current?.clear()
        onChangeRef.current?.(true)
      }

      const pad = new SignaturePadLib(canvas, {
        minWidth: 0.6,
        maxWidth: 2.2,
        penColor: '#0f172a',
        backgroundColor: '#ffffff',
      })
      padRef.current = pad

      // Notifie le parent quand l'utilisateur termine un trait
      pad.addEventListener('endStroke', () => {
        onChangeRef.current?.(pad.isEmpty())
      })

      resize()
      window.addEventListener('resize', resize)
      return () => {
        window.removeEventListener('resize', resize)
        pad.off()
        padRef.current = null
      }
    }, [])

    useImperativeHandle(
      ref,
      () => ({
        toDataURL() {
          const pad = padRef.current
          if (!pad || pad.isEmpty()) return null
          return pad.toDataURL('image/png')
        },
        clear() {
          padRef.current?.clear()
          onChangeRef.current?.(true)
        },
        isEmpty() {
          return padRef.current?.isEmpty() ?? true
        },
      }),
      [],
    )

    return (
      <div
        className={cn(
          'rounded-md border border-slate-300 bg-white shadow-inner',
          className,
        )}
      >
        <canvas
          ref={canvasRef}
          className="block h-48 w-full rounded-md"
          aria-label="Zone de signature"
        />
      </div>
    )
  },
)
SignaturePad.displayName = 'SignaturePad'
