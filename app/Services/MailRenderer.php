<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Rendu des emails transactionnels.
 *
 * On expose deux variantes :
 *  - renderText() : version texte brut (fallback si HTML bloque)
 *  - renderHtml() : version HTML simple, inline-styles uniquement,
 *    sans dependance externe (pas de moteur de template).
 *
 * Tous les contenus utilisateur sont passes a htmlspecialchars() pour
 * neutraliser tout risque XSS (les variables sont fournies par le code,
 * mais on reste defensif).
 */
final class MailRenderer
{
    public static function renderText(string $template, array $vars): string
    {
        return match ($template) {
            'otp_signature' => sprintf(
                "Votre code de signature : %s\nValidite : %d minutes.\n",
                (string) ($vars['code'] ?? ''),
                (int) ($vars['ttl_minutes'] ?? 5),
            ),
            'signature_done_locataire' => sprintf(
                "Votre signature du document \"%s\" a bien ete enregistree le %s.\n",
                (string) ($vars['document'] ?? ''),
                (string) ($vars['signedAt'] ?? ''),
            ),
            'signature_done_manager' => sprintf(
                "Le locataire %s a signe le document \"%s\" le %s.\n",
                (string) ($vars['locataire'] ?? ''),
                (string) ($vars['document'] ?? ''),
                (string) ($vars['signedAt'] ?? ''),
            ),
            'signature_finalized' => sprintf(
                "Votre document \"%s\" est disponible signe :\n%s\n",
                (string) ($vars['document'] ?? ''),
                (string) ($vars['pdfUrl'] ?? ''),
            ),
            'password_reset' => sprintf(
                "Voici le lien pour reinitialiser votre mot de passe :\n%s\n\nValidite : %d minutes.\nSi vous n'avez pas demande cette operation, ignorez ce mail.\n",
                (string) ($vars['resetUrl'] ?? ''),
                (int) ($vars['ttl_minutes'] ?? 30),
            ),
            default => "Notification Espace Privatif\n",
        };
    }

    public static function renderHtml(string $template, array $vars): string
    {
        $body = match ($template) {
            'otp_signature' => self::otpHtml(
                (string) ($vars['code'] ?? ''),
                (int) ($vars['ttl_minutes'] ?? 5),
            ),
            'signature_done_locataire' => self::doneLocataireHtml(
                (string) ($vars['document'] ?? ''),
                (string) ($vars['signedAt'] ?? ''),
            ),
            'signature_done_manager' => self::doneManagerHtml(
                (string) ($vars['locataire'] ?? ''),
                (string) ($vars['document'] ?? ''),
                (string) ($vars['signedAt'] ?? ''),
            ),
            'signature_finalized' => self::finalizedHtml(
                (string) ($vars['document'] ?? ''),
                (string) ($vars['pdfUrl'] ?? ''),
            ),
            'password_reset' => self::passwordResetHtml(
                (string) ($vars['resetUrl'] ?? ''),
                (int) ($vars['ttl_minutes'] ?? 30),
            ),
            default => '<p>Notification Espace Privatif</p>',
        };
        return self::layout($body);
    }

    private static function layout(string $bodyHtml): string
    {
        return <<<HTML
<!doctype html>
<html lang="fr">
  <body style="margin:0;padding:0;background:#f5f6fa;font-family:Helvetica,Arial,sans-serif;color:#1a1f36;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 0;">
      <tr><td align="center">
        <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;border:1px solid #e3e8ef;">
          <tr><td style="padding:24px 32px;border-bottom:1px solid #eef0f3;">
            <span style="font-size:18px;font-weight:600;color:#1e2761;">Espace Privatif</span>
          </td></tr>
          <tr><td style="padding:28px 32px;font-size:15px;line-height:1.55;">
            {$bodyHtml}
          </td></tr>
          <tr><td style="padding:18px 32px;border-top:1px solid #eef0f3;font-size:11px;color:#6b7280;">
            Email automatique, merci de ne pas y repondre.<br>
            Realsoft - realsoft.espace.privatif
          </td></tr>
        </table>
      </td></tr>
    </table>
  </body>
</html>
HTML;
    }

    private static function otpHtml(string $code, int $ttl): string
    {
        $code = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        return <<<HTML
<p style="margin:0 0 12px;">Voici votre code de signature :</p>
<p style="margin:16px 0;text-align:center;">
  <span style="display:inline-block;padding:14px 22px;border:1px solid #1e2761;border-radius:6px;font-size:24px;letter-spacing:6px;font-weight:700;color:#1e2761;font-family:Consolas,monospace;">
    {$code}
  </span>
</p>
<p style="margin:12px 0 0;color:#6b7280;font-size:13px;">
  Ce code est valable {$ttl} minutes. Ne le partagez jamais.
</p>
HTML;
    }

    private static function doneLocataireHtml(string $document, string $signedAt): string
    {
        $document = htmlspecialchars($document, ENT_QUOTES, 'UTF-8');
        $signedAt = htmlspecialchars($signedAt, ENT_QUOTES, 'UTF-8');
        return <<<HTML
<p style="margin:0 0 12px;">Bonjour,</p>
<p style="margin:0 0 12px;">
  Votre signature du document <strong>{$document}</strong> a bien ete enregistree
  le <strong>{$signedAt}</strong>.
</p>
<p style="margin:0;color:#6b7280;font-size:13px;">
  Vous recevrez le document signe finalise des sa validation.
</p>
HTML;
    }

    private static function doneManagerHtml(string $locataire, string $document, string $signedAt): string
    {
        $locataire = htmlspecialchars($locataire, ENT_QUOTES, 'UTF-8');
        $document = htmlspecialchars($document, ENT_QUOTES, 'UTF-8');
        $signedAt = htmlspecialchars($signedAt, ENT_QUOTES, 'UTF-8');
        return <<<HTML
<p style="margin:0 0 12px;">Bonjour,</p>
<p style="margin:0 0 12px;">
  Le locataire <strong>{$locataire}</strong> a signe le document
  <strong>{$document}</strong> le <strong>{$signedAt}</strong>.
</p>
HTML;
    }

    private static function passwordResetHtml(string $resetUrl, int $ttl): string
    {
        $url = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
        return <<<HTML
<p style="margin:0 0 12px;">Bonjour,</p>
<p style="margin:0 0 16px;">
  Vous avez demande la reinitialisation de votre mot de passe.
  Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe.
</p>
<p style="margin:16px 0;">
  <a href="{$url}" style="display:inline-block;padding:12px 22px;background:#1e2761;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">
    Reinitialiser mon mot de passe
  </a>
</p>
<p style="margin:8px 0 0;font-size:12px;color:#6b7280;">
  Ce lien est valable {$ttl} minutes. Si vous n'avez pas demande cette
  operation, ignorez ce mail : votre mot de passe restera inchange.
</p>
<p style="margin:8px 0 0;font-size:12px;color:#6b7280;word-break:break-all;">
  Lien direct : {$url}
</p>
HTML;
    }

    private static function finalizedHtml(string $document, string $pdfUrl): string
    {
        $document = htmlspecialchars($document, ENT_QUOTES, 'UTF-8');
        $pdfUrl = htmlspecialchars($pdfUrl, ENT_QUOTES, 'UTF-8');
        return <<<HTML
<p style="margin:0 0 12px;">Bonjour,</p>
<p style="margin:0 0 16px;">
  Votre document <strong>{$document}</strong> est disponible signe.
</p>
<p style="margin:16px 0;">
  <a href="{$pdfUrl}" style="display:inline-block;padding:12px 22px;background:#1e2761;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">
    Telecharger le PDF
  </a>
</p>
<p style="margin:8px 0 0;font-size:12px;color:#6b7280;word-break:break-all;">
  Lien direct : {$pdfUrl}
</p>
HTML;
    }
}
