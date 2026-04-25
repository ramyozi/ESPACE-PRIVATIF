# Architecture technique ESPACE-PRIVATIF

## 1. Vue d'ensemble

Architecture trois tiers classique, orientée pragmatisme. Pas de microservices. Un seul socle PHP, une base MySQL, un serveur WebSocket dédié, un service de mail.

```
+----------------+        HTTPS         +------------------------+
|   Navigateur   |  <--------------->   |   PHP (Slim ou natif)  |
+----------------+                      |   Front + API          |
        |                               +-----------+------------+
        |                                           |
        |                                  PDO      |     SMTP / API mail
        |                                           v
        |                               +-----------+------------+
        |                               |       MySQL 8          |
        |                               +-----------+------------+
        |                                           ^
        |                                           | (lecture/écriture)
        |                               +-----------+------------+
        +-- WebSocket WSS ------------> |  Serveur WS (Ratchet)  |
                                        +-----------+------------+
                                                    |
                                                    v
                                        +------------------------+
                                        |        SOTHIS          |
                                        +------------------------+
```

## 2. Choix techniques

| Brique | Choix | Justification |
|--------|-------|---------------|
| Langage backend | PHP 8.2 | Imposé par le contexte. Typage moderne, performances correctes. |
| Framework | Slim 4 | Léger, routage propre, courbe d'apprentissage faible, suffisant pour 4 jours. |
| Base de données | MySQL 8 | Imposé. Support JSON natif utile pour métadonnées. |
| Accès BDD | PDO + repositories | Simple, sécurisé, pas de surcoût d'ORM. |
| Templates | Twig | Sécurité par défaut (échappement), rapide à mettre en place. |
| WebSocket | Ratchet (PHP) | Reste dans la même stack, facile à déployer en service systemd. |
| Mail | Symfony Mailer | Robuste, support SMTP, templates Twig. |
| Auth | Sessions PHP natives + token signé | Pas besoin de JWT pour ce périmètre. |
| Front | Twig + Vanilla JS + PDF.js + signature_pad | Aucune lourdeur SPA. |
| Tests | PHPUnit pour la logique critique | Focalisés sur la signature et les transitions d'état. |
| Conteneurisation | Docker Compose | Reproductibilité locale et déploiement simple. |

## 3. Découpage du backend

```
src/
  Auth/             authentification, sessions, OTP
  Document/         consultation, états, transitions
  Signature/        capture, validation OTP, persistance
  Sothis/           client WebSocket, file d'attente, mapping
  Mail/             services et templates
  Tenant/           résolution du tenant courant, scopes
  Audit/            journalisation immuable
  Http/             middlewares (CSRF, auth, tenant, rate-limit)
  Persistence/      PDO, repositories, migrations
config/
public/             point d'entrée index.php
bin/
  ws-server.php     entrée du serveur Ratchet
templates/
migrations/
tests/
```

Chaque dossier expose une interface claire utilisée par les contrôleurs. Pas de logique métier dans les contrôleurs.

## 4. API entre ESPACE-PRIVATIF et SOTHIS

### 4.1 Entrée depuis SOTHIS (dépôt de document)

SOTHIS pousse un document via un endpoint REST sécurisé.

```
POST /api/sothis/documents
Authorization: Bearer <jeton signé HS256>
Content-Type: application/json

{
  "tenant_id": "T-001",
  "document_id": "DOC-2026-0001",
  "tenant_user_id": "LOC-1234",
  "type": "bail",
  "title": "Bail résidence Lilas",
  "pdf_url": "https://sothis.local/files/abcdef.pdf",
  "pdf_sha256": "a3f5...",
  "deadline": "2026-05-10T18:00:00+02:00",
  "fields": [
    {"name": "signature_locataire", "page": 3, "x": 120, "y": 600, "w": 200, "h": 60}
  ],
  "callback_url": "https://sothis.local/api/v1/signatures/callback"
}
```

Réponse :

```
201 Created
{ "status": "accepted", "espace_doc_id": 8821 }
```

### 4.2 Notification sortante vers SOTHIS

Après signature, ESPACE-PRIVATIF transmet les données via WebSocket. Si le canal est indisponible, fallback HTTPS sur callback_url.

## 5. WebSocket

### 5.1 Rôle

Canal temps réel pour transmettre les signatures à SOTHIS et recevoir le PDF final régénéré.

### 5.2 Connexion

- URL : wss://espace-privatif.local/ws
- Authentification par jeton signé passé en query string, vérifié côté serveur.
- Un seul canal SOTHIS partagé, multi-tenant. Le tenant_id est porté dans chaque message.

### 5.3 Format des messages

Tous les messages sont JSON, avec une enveloppe commune.

```
{
  "type": "<event>",
  "version": 1,
  "message_id": "uuid-v4",
  "tenant_id": "T-001",
  "ts": "2026-04-25T10:32:00Z",
  "payload": { ... }
}
```

### 5.4 Événements

| Type | Sens | Description |
|------|------|-------------|
| signature.completed | EP -> SOTHIS | Signature validée, données et image transmises |
| signature.failed | EP -> SOTHIS | Refus ou échec définitif |
| document.finalized | SOTHIS -> EP | PDF signé final disponible (URL ou base64) |
| ack | les deux sens | Accusé de réception de message_id |
| ping / pong | les deux sens | Maintien du lien |

### 5.5 Exemple signature.completed

```
{
  "type": "signature.completed",
  "version": 1,
  "message_id": "f0e1...",
  "tenant_id": "T-001",
  "ts": "2026-04-25T10:32:00Z",
  "payload": {
    "document_id": "DOC-2026-0001",
    "tenant_user_id": "LOC-1234",
    "signed_at": "2026-04-25T10:31:50Z",
    "signature_image_b64": "iVBORw0KGgoAA...",
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

### 5.6 Fiabilité

- Toute émission est persistée en BDD avant envoi (table outbox).
- Un worker rejoue les messages non acquittés.
- Idempotence garantie par message_id côté SOTHIS.

## 6. Gestion des mails

- Service unique MailService, templates Twig.
- File d'attente simple en BDD (mail_queue) traitée par un worker CLI.
- En cas d'échec SMTP, retry avec backoff. Trois tentatives, puis alerte.

## 7. Sessions et authentification

- Cookies HttpOnly, Secure, SameSite=Lax.
- Régénération de l'identifiant à la connexion et à l'élévation de privilège.
- CSRF token par formulaire, vérifié systématiquement.
- Rate limiting sur /login et /signature/otp (10 req/min/IP).

## 8. Logs et supervision

- Logs applicatifs : Monolog, niveau INFO en prod, rotation quotidienne.
- Logs d'audit : table dédiée, append only, hash chaîné par tenant pour détecter toute altération.
- Logs WebSocket : messages bruts horodatés, conservés 90 jours.
- Health check : /health (BDD, file mail, WS).

## 9. Déploiement

- Trois conteneurs Docker : php-fpm + nginx, mysql, ws-server.
- Variables sensibles via .env hors dépôt.
- HTTPS terminé sur nginx (certificat Let's Encrypt en prod).
- Sauvegardes MySQL quotidiennes chiffrées.

## 10. Améliorations possibles

- Signature qualifiée via prestataire eIDAS (DocuSign, Yousign) si exigence légale.
- Horodatage RFC 3161 sur les preuves de signature.
- Stockage des PDF en S3 compatible plutôt que sur disque.
- Mise en place d'un Redis pour les sessions et le rate limiting si la charge augmente.
- Métriques Prometheus + tableau Grafana.
