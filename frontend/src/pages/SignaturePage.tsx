import { useEffect, useRef, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { ArrowLeft, Eraser, MailCheck, ShieldCheck } from 'lucide-react'
import { Layout } from '@/components/Layout'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Loader } from '@/components/Loader'
import { ErrorMessage } from '@/components/ErrorMessage'
import { SuccessMessage } from '@/components/SuccessMessage'
import { SignaturePad, type SignaturePadHandle } from '@/components/SignaturePad'
import { OtpForm } from '@/components/OtpForm'
import { ApiError, api, type DocumentItem } from '@/services/api'

type Phase = 'loading' | 'sign' | 'done'

/**
 * Page dediee au parcours de signature electronique :
 *
 *  1. Au mount : on charge le document et on declenche /sign/start si besoin
 *     (cela envoie l'OTP par email).
 *  2. L'utilisateur trace sa signature et saisit l'OTP.
 *  3. On envoie /sign/complete avec le PNG en data URL.
 *  4. Sur succes, on affiche un message et on revient au tableau de bord.
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

  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [info, setInfo] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  /**
   * Initialisation : on charge le document et, s il est en attente,
   * on declenche /sign/start (envoi OTP). Si la signature est deja en cours,
   * on ne renvoie pas un nouveau code, l utilisateur peut demander un renvoi.
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
          // Signature deja amorcee : OTP deja envoye, on demande la saisie.
          // L utilisateur peut demander un nouvel envoi si necessaire.
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

    setSubmitting(true)
    try {
      await api.completeSignature(doc.id, { otp, signature: dataUrl })
      setSuccess('Document signe avec succes !')
      setPhase('done')
    } catch (e) {
      // Sur otp_invalid on garde l'utilisateur sur place pour qu'il reessaie
      setError(e instanceof ApiError ? e.message : 'Signature impossible')
    } finally {
      setSubmitting(false)
    }
  }

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

      {phase === 'sign' && doc && (
        <Card>
          <CardHeader>
            <CardTitle>Signature electronique</CardTitle>
            <CardDescription>
              Document {doc.title} (reference {doc.sothisDocumentId})
            </CardDescription>
          </CardHeader>

          <CardContent className="space-y-5">
            {info && (
              <div className="flex items-start gap-2 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-700">
                <MailCheck className="mt-0.5 h-4 w-4 shrink-0" />
                <span>{info}</span>
              </div>
            )}

            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <span className="text-sm font-medium text-slate-700">
                  Tracez votre signature
                </span>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={handleClearSignature}
                  disabled={submitting}
                >
                  <Eraser className="h-4 w-4" /> Effacer
                </Button>
              </div>
              <SignaturePad ref={padRef} onChange={setSignatureEmpty} />
              {signatureEmpty && (
                <p className="text-xs text-slate-500">
                  Utilisez votre souris, votre doigt ou un stylet.
                </p>
              )}
            </div>

            <OtpForm value={otp} onChange={setOtp} disabled={submitting} />

            <div className="flex items-start gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
              <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0 text-slate-500" />
              <span>
                En validant, vous reconnaissez avoir lu le document et acceptez
                de le signer electroniquement. Une trace est conservee
                (date, heure, IP, code OTP).
              </span>
            </div>

            {error && <ErrorMessage message={error} />}
          </CardContent>

          <CardFooter className="flex flex-wrap items-center justify-between gap-3">
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
              disabled={submitting || signatureEmpty || otp.length !== 6}
            >
              {submitting ? (
                <>
                  <Loader size={16} /> Validation...
                </>
              ) : (
                'Valider la signature'
              )}
            </Button>
          </CardFooter>
        </Card>
      )}

      {phase === 'done' && (
        <Card>
          <CardContent className="space-y-4 pt-6">
            {success && <SuccessMessage message={success} />}
            {error && <ErrorMessage message={error} />}
            <div className="flex justify-end">
              <Button onClick={() => navigate('/')}>Retour aux documents</Button>
            </div>
          </CardContent>
        </Card>
      )}
    </Layout>
  )
}
