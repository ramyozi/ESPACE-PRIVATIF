import { useEffect, useRef, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import {
  ArrowLeft,
  CheckCircle2,
  Eraser,
  Loader2,
  MailCheck,
  PenLine,
  ShieldCheck,
} from 'lucide-react'
import { Layout } from '@/components/Layout'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Loader } from '@/components/Loader'
import { ErrorMessage } from '@/components/ErrorMessage'
import { SuccessMessage } from '@/components/SuccessMessage'
import { SignaturePad, type SignaturePadHandle } from '@/components/SignaturePad'
import { OtpForm } from '@/components/OtpForm'
import { SignatureStepper, type SignatureStep } from '@/components/SignatureStepper'
import { DocumentPreviewCard } from '@/components/DocumentPreviewCard'
import { cn } from '@/lib/cn'
import { ApiError, api, type DocumentItem } from '@/services/api'

type Phase = 'loading' | 'sign' | 'submitting' | 'done'

/**
 * Page dediee au parcours de signature electronique.
 *
 *  1. Au mount : on charge le document et on declenche /sign/start si besoin
 *     (cela envoie l'OTP par email).
 *  2. L'utilisateur trace sa signature et saisit l'OTP.
 *  3. On envoie /sign/complete avec le PNG en data URL.
 *  4. Sur succes, on affiche un message et on revient au tableau de bord.
 *
 * Cette refactorisation est strictement UI : aucune logique metier, ni route,
 * ni endpoint, ni service backend n'a ete modifie.
 */
export function SignaturePage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const documentId = Number(id)

  const padRef = useRef<SignaturePadHandle | null>(null)
  const [doc, setDoc] = useState<DocumentItem | null>(null)
  const [phase, setPhase] = useState<Phase>('loading')
  const [otp, setOtp] = useState('')
  const [signatureEmpty, setSignatureEmpty] = useState(true)

  const [error, setError] = useState<string | null>(null)
  const [info, setInfo] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  /**
   * Initialisation : on charge le document et, s'il est en attente,
   * on declenche /sign/start (envoi OTP). Si la signature est deja en cours,
   * on ne renvoie pas un nouveau code, l'utilisateur peut demander un renvoi.
   */
  useEffect(() => {
    if (Number.isNaN(documentId)) {
      setError('Document invalide')
      setPhase('done')
      return
    }
    let cancelled = false
    ;(async () => {
      try {
        const data = await api.getDocument(documentId)
        if (cancelled) return
        setDoc(data)

        if (data.state === 'en_attente_signature') {
          await api.startSignature(data.id)
          if (!cancelled) {
            setInfo('Un code de signature a ete envoye par email.')
            setPhase('sign')
          }
        } else if (data.state === 'signature_en_cours') {
          setInfo('Saisissez le code recu par email pour valider la signature.')
          setPhase('sign')
        } else if (data.state === 'signe' || data.state === 'signe_valide') {
          setSuccess('Ce document a deja ete signe.')
          setPhase('done')
        } else {
          setError("Ce document ne peut plus etre signe")
          setPhase('done')
        }
      } catch (e) {
        if (cancelled) return
        setError(e instanceof ApiError ? e.message : 'Erreur lors du chargement')
        setPhase('done')
      }
    })()
    return () => {
      cancelled = true
    }
  }, [documentId])

  // Etape courante du stepper, derivee de l'etat de la page.
  // signature tracee + OTP rempli => on est conceptuellement sur "otp" pret a valider
  const currentStep: SignatureStep =
    phase === 'done'
      ? 'done'
      : !signatureEmpty
        ? otp.length === 6
          ? 'otp'
          : 'otp'
        : 'signature'

  async function handleResendOtp() {
    if (!doc) return
    setError(null)
    setInfo(null)
    try {
      await api.startSignature(doc.id)
      setInfo('Un nouveau code vient d etre envoye.')
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Envoi impossible')
    }
  }

  function handleClearSignature() {
    padRef.current?.clear()
    setSignatureEmpty(true)
  }

  async function handleSubmit() {
    if (!doc) return
    setError(null)
    setSuccess(null)

    if (otp.length !== 6) {
      setError('Le code OTP doit comporter 6 chiffres')
      return
    }
    const dataUrl = padRef.current?.toDataURL()
    if (!dataUrl) {
      setError('Veuillez tracer votre signature avant de valider')
      return
    }

    setPhase('submitting')
    try {
      await api.completeSignature(doc.id, { otp, signature: dataUrl })
      setSuccess('Document signe avec succes !')
      setPhase('done')
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Signature impossible')
      setPhase('sign')
    }
  }

  const submitting = phase === 'submitting'
  const canSubmit = !submitting && !signatureEmpty && otp.length === 6

  return (
    <Layout>
      <div className="mb-4">
        <Button variant="ghost" size="sm" onClick={() => navigate('/')}>
          <ArrowLeft className="h-4 w-4" /> Retour aux documents
        </Button>
      </div>

      {phase === 'loading' && (
        <div className="flex items-center justify-center py-16">
          <Loader size={32} />
        </div>
      )}

      {(phase === 'sign' || phase === 'submitting') && doc && (
        <div className="space-y-6">
          {/* En-tete : titre + sous-titre + banderole de statut */}
          <header className="space-y-3">
            <div className="flex flex-wrap items-end justify-between gap-3">
              <div>
                <h1 className="text-2xl font-semibold text-slate-900">
                  Signature electronique
                </h1>
                <p className="text-sm text-slate-500">
                  Document <span className="font-medium text-slate-700">{doc.title}</span>
                </p>
              </div>
              <StatusPill phase={phase} signatureEmpty={signatureEmpty} otpLen={otp.length} />
            </div>

            {/* Stepper visuel */}
            <Card>
              <CardContent className="py-5">
                <SignatureStepper
                  current={currentStep}
                  signatureDone={!signatureEmpty}
                />
              </CardContent>
            </Card>
          </header>

          {/* Message d'info (OTP envoye, renvoye, etc.) */}
          {info && (
            <div className="flex items-start gap-2 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-700">
              <MailCheck className="mt-0.5 h-4 w-4 shrink-0" aria-hidden />
              <span>{info}</span>
            </div>
          )}

          {/* Layout 2 colonnes : preview a gauche, actions a droite */}
          <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
            <DocumentPreviewCard document={doc} />

            <Card>
              <CardContent className="space-y-6 pt-6">
                {/* Section 1 : signature */}
                <section className="space-y-3">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <PenLine className="h-4 w-4 text-blue-600" aria-hidden />
                      <h2 className="text-sm font-semibold text-slate-800">
                        1. Tracez votre signature
                      </h2>
                    </div>
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      onClick={handleClearSignature}
                      disabled={submitting || signatureEmpty}
                    >
                      <Eraser className="h-4 w-4" /> Effacer
                    </Button>
                  </div>
                  <SignaturePad
                    ref={padRef}
                    onChange={setSignatureEmpty}
                    isEmpty={signatureEmpty}
                  />
                </section>

                {/* Section 2 : OTP */}
                <section className="space-y-3">
                  <div className="flex items-center gap-2">
                    <MailCheck className="h-4 w-4 text-blue-600" aria-hidden />
                    <h2 className="text-sm font-semibold text-slate-800">
                      2. Saisissez le code recu par email
                    </h2>
                  </div>
                  <OtpForm value={otp} onChange={setOtp} disabled={submitting} />
                </section>

                {/* Mention legale */}
                <div className="flex items-start gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                  <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0 text-slate-500" aria-hidden />
                  <span>
                    En validant, vous reconnaissez avoir lu le document et acceptez
                    de le signer electroniquement. Une trace est conservee
                    (date, heure, IP, code OTP).
                  </span>
                </div>

                {error && <ErrorMessage message={error} />}

                {/* Actions */}
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                  <Button
                    type="button"
                    variant="ghost"
                    onClick={handleResendOtp}
                    disabled={submitting}
                  >
                    Renvoyer un code
                  </Button>
                  <Button
                    type="button"
                    onClick={handleSubmit}
                    disabled={!canSubmit}
                    className="min-w-[180px]"
                  >
                    {submitting ? (
                      <>
                        <Loader2 className="h-4 w-4 animate-spin" /> Validation...
                      </>
                    ) : (
                      <>
                        <CheckCircle2 className="h-4 w-4" /> Valider la signature
                      </>
                    )}
                  </Button>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      )}

      {phase === 'done' && (
        <Card className="mx-auto max-w-xl">
          <CardContent className="space-y-5 pt-8 pb-6 text-center">
            {success && (
              <div className="space-y-3">
                <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-green-100 text-green-600">
                  <CheckCircle2 className="h-8 w-8" aria-hidden />
                </div>
                <SuccessMessage message={success} />
                <p className="text-sm text-slate-500">
                  Vous allez recevoir un email de confirmation. Le document signe
                  sera disponible apres validation par notre service.
                </p>
              </div>
            )}
            {error && <ErrorMessage message={error} />}
            <div className="flex justify-center">
              <Button onClick={() => navigate('/')}>Retour aux documents</Button>
            </div>
          </CardContent>
        </Card>
      )}
    </Layout>
  )
}

/**
 * Pastille de statut affichee dans l'en-tete : reflete l'etape en cours.
 * Trois etats visuels :
 *  - bleu clair : "Document pret a signer" (rien fait encore)
 *  - bleu : "Signature en cours" (un trait au moins, pas encore valide)
 *  - amber : "Validation en cours" (submit en cours)
 */
function StatusPill({
  phase,
  signatureEmpty,
  otpLen,
}: {
  phase: Phase
  signatureEmpty: boolean
  otpLen: number
}) {
  if (phase === 'submitting') {
    return (
      <span className="inline-flex items-center gap-2 rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-800">
        <Loader2 className="h-3 w-3 animate-spin" aria-hidden />
        Validation en cours
      </span>
    )
  }

  const inProgress = !signatureEmpty || otpLen > 0
  return (
    <span
      className={cn(
        'inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium',
        inProgress
          ? 'bg-blue-100 text-blue-800'
          : 'bg-slate-100 text-slate-700',
      )}
    >
      <span
        className={cn(
          'h-2 w-2 rounded-full',
          inProgress ? 'bg-blue-500' : 'bg-slate-400',
        )}
        aria-hidden
      />
      {inProgress ? 'Signature en cours' : 'Document pret a signer'}
    </span>
  )
}
