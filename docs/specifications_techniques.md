# Spécifications techniques

## 1. Endpoints HTTP côté ESPACE-PRIVATIF

### 1.1 Endpoints utilisateur (locataire)

| Méthode | URL | Description |
|---------|-----|-------------|
| GET | / | Page d'accueil |
| GET | /login | Formulaire de connexion |
| POST | /login | Authentification |
| POST | /login/magic | Demande de lien magique |
| GET | /login/magic/{token} | Connexion par lien magique |
| POST | /logout | Déconnexion |
| GET | /documents | Liste des documents du locataire |
| GET | /documents/{id} | Détail et aperçu PDF |
| GET | /documents/{id}/sign | Page de signature |
| POST | /documents/{id}/sign/start | Génère et envoie l'OTP |
| POST | /documents/{id}/sign/complete | Soumet image + OTP |
| POST | /documents/{id}/refuse | Refus motivé |
| GET | /documents/{id}/download | Téléchargement signé (URL signée) |

### 1.2 Endpoints SOTHIS (entrée serveur à serveur)

| Méthode | URL | Description |
|---------|-----|-------------|
| POST | /api/sothis/documents | Dépôt d'un document |
| POST | /api/sothis/users | Création ou mise à jour locataire |
| POST | /api/sothis/finalize | Fallback HTTPS si WS indisponible |

Authentification : Authorization Bearer <jwt HS256>. Clé partagée par tenant (sothis_api_key_hash).

### 1.3 Endpoints internes

| Méthode | URL | Description |
|---------|-----|-------------|
| GET | /health | État technique (DB, WS, mail) |

## 2. Format des messages WebSocket

Enveloppe commune :

```json
{
  "type": "string",
  "version": 1,
  "message_id": "uuid-v4",
  "tenant_id": "T-001",
  "ts": "ISO 8601",
  "payload": { }
}
```

### 2.1 signature.completed (EP -> SOTHIS)

```json
{
  "type": "signature.completed",
  "version": 1,
  "message_id": "f0e1c4...",
  "tenant_id": "T-001",
  "ts": "2026-04-25T10:32:00Z",
  "payload": {
    "document_id": "DOC-2026-0001",
    "tenant_user_id": "LOC-1234",
    "signed_at": "2026-04-25T10:31:50.123Z",
    "signature_image_b64": "iVBOR...",
    "signature_image_sha256": "ab12...",
    "consent": {
      "method": "otp_email",
      "otp_validated_at": "2026-04-25T10:31:45Z"
    },
    "context": {
      "ip": "82.x.x.x",
      "user_agent": "Mozilla/5.0 ..."
    },
    "audit_hash": "sha256:..."
  }
}
```

### 2.2 signature.failed (EP -> SOTHIS)

```json
{
  "type": "signature.failed",
  "payload": {
    "document_id": "DOC-2026-0001",
    "reason": "refused_by_user",
    "reason_text": "Loyer incorrect"
  }
}
```

### 2.3 document.finalized (SOTHIS -> EP)

```json
{
  "type": "document.finalized",
  "payload": {
    "document_id": "DOC-2026-0001",
    "pdf_url": "https://sothis.local/files/signed/abcdef.pdf",
    "pdf_sha256": "1234..."
  }
}
```

### 2.4 ack (les deux sens)

```json
{
  "type": "ack",
  "payload": { "ack_message_id": "f0e1c4..." }
}
```

## 3. Gestion des événements

- Toute émission EP -> SOTHIS passe par la table ws_outbox (persistée d'abord).
- Le worker outbox lit les messages pending et les transmet via le WS.
- Si pas d'ack en 30 s, retry exponentiel (1, 5, 30, 120 min). 5 tentatives max puis statut failed et alerte.
- Toute réception SOTHIS -> EP est traitée dans une transaction unique (mise à jour document + log audit + queue mail).
- Idempotence côté EP : la table ws_inbox (équivalent) garde les message_id traités les 7 derniers jours.

## 4. Authentification SOTHIS -> EP

- JWT HS256 signé avec la clé partagée du tenant.
- Claims attendus : iss, aud, iat, exp (5 min max), tenant_id.
- L'IP source peut être restreinte à une whitelist par tenant.

## 5. Headers HTTP de sécurité

- Strict-Transport-Security: max-age=31536000; includeSubDomains
- Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'
- X-Frame-Options: DENY
- X-Content-Type-Options: nosniff
- Referrer-Policy: strict-origin-when-cross-origin
- Permissions-Policy: camera=(), microphone=(), geolocation=()

## 6. Conventions de réponse API

```json
{
  "status": "ok | error",
  "data": { },
  "error": { "code": "string", "message": "string" }
}
```

Codes d'erreur normalisés : auth_required, forbidden, document_not_found, invalid_state, otp_invalid, otp_locked, internal_error.

## 7. Limites et quotas

- Taille image signature : 200 Ko max.
- Taille body POST signature : 1 Mo max.
- Rate limit /sign/complete : 5 tentatives par heure et par utilisateur.
- Rate limit /login : 10 par minute par IP.

## 8. Logs structurés

Format JSON, un événement par ligne :

```json
{"ts":"...","level":"INFO","tenant":"T-001","user":1234,"action":"sign_complete","doc":8821,"ip":"..."}
```

Champ correlation_id propagé sur toute la chaîne d'une requête.
