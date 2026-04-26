# ESPACE-PRIVATIF

Module de signature electronique en PHP 8.2 / Slim 4 / MySQL 8 / Ratchet.
Conception detaillee dans [docs/](docs/) et [diagrams/](diagrams/).

## Stack

- PHP 8.2 + Slim 4
- MySQL 8 (multi-tenant par colonne tenant_id)
- Ratchet (WebSocket) pour le canal SOTHIS
- Symfony Mailer
- PHPUnit pour les tests
- Docker Compose

## Demarrage rapide

```bash
cp .env.example .env
docker-compose up --build -d

# Installation des dependances PHP a l'interieur du conteneur
docker-compose exec app composer install

# Seed des locataires de demo (mot de passe : demo1234)
docker-compose exec app php bin/seed-users.php
```

L'API repond sur http://localhost:8080.

| Service | URL |
|---------|-----|
| API HTTP | http://localhost:8080 |
| WebSocket | ws://localhost:8081 |
| MySQL | localhost:3307 (user `app` / pass `app_secret`) |

Verification :

```bash
curl http://localhost:8080/health
```

## Comptes de demo

| Email | Mot de passe | Documents |
|-------|--------------|-----------|
| alice@example.test | demo1234 | DOC-2026-0001 (bail) |
| bob@example.test | demo1234 | DOC-2026-0002 (avenant) |

## Endpoints

| Methode | URL | Auth | CSRF | Description |
|---------|-----|------|------|-------------|
| GET  | /health | - | - | Sante service + BDD |
| POST | /api/auth/login | - | - | Connexion par email/mot de passe |
| POST | /api/auth/logout | session | requis | Deconnexion |
| GET  | /api/auth/me | session | - | Profil du locataire connecte |
| GET  | /api/auth/csrf-token | session | - | Recupere un jeton CSRF |
| GET  | /api/documents | session | - | Liste des documents du locataire |
| GET  | /api/documents/{id} | session | - | Detail d'un document |
| POST | /api/documents/{id}/sign/start | session | requis | Lance la signature et envoie l'OTP |
| POST | /api/documents/{id}/sign/complete | session | requis | Valide la signature avec OTP + image |
| POST | /api/documents/{id}/refuse | session | requis | Refus motive |
| POST | /api/sothis/document/finalized | API key | - | Notification de finalisation par SOTHIS |

## Securite

### Headers HTTP

Le middleware `SecurityHeadersMiddleware` ajoute sur chaque reponse :

- `X-Frame-Options: DENY`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=()`
- `Content-Security-Policy: default-src 'self'; img-src 'self' data:; ...`

### Protection CSRF

Sur les routes mutantes (logout, sign/start, sign/complete, refuse), un token CSRF est exige.

Cycle :

1. Apres `/api/auth/login`, la reponse contient `csrfToken`.
2. Ou bien, le client peut le recuperer a la demande via `GET /api/auth/csrf-token`.
3. Le client renvoie le token dans l'en-tete `X-CSRF-Token` (ou dans le body `csrf_token`).
4. Si absent ou invalide, le serveur repond `403` avec le code `csrf_invalid`.

`/api/auth/login` est volontairement exempt (premier appel sans session).
`/api/sothis/document/finalized` est exempt (auth par cle API serveur a serveur).

### Authentification SOTHIS

Cle API statique passee en header `X-Sothis-Key`. La valeur attendue vient
de `SOTHIS_API_KEY` dans `.env`. Comparaison en temps constant (`hash_equals`).

## Tester avec curl ou Postman

Toutes les requetes utilisent les cookies de session : pensez a activer
"Send cookies" dans Postman ou a utiliser `-c cookies.txt -b cookies.txt` avec curl.

### 1. Login (recupere aussi le csrfToken)

```bash
curl -i -c cookies.txt -X POST http://localhost:8080/api/auth/login \
     -H "Content-Type: application/json" \
     -d '{"email":"alice@example.test","password":"demo1234"}'
```

### 2. Profil

```bash
curl -b cookies.txt http://localhost:8080/api/auth/me
```

### 3. Liste des documents

```bash
curl -b cookies.txt http://localhost:8080/api/documents
```

### 4. Demarrer la signature (CSRF requis)

```bash
TOKEN=$(curl -s -b cookies.txt http://localhost:8080/api/auth/csrf-token | jq -r .data.csrfToken)

curl -b cookies.txt -X POST http://localhost:8080/api/documents/1/sign/start \
     -H "X-CSRF-Token: $TOKEN"
```

L'OTP est trace dans `var/log/app.log` cote conteneur, et dans la table `mail_queue` :

```bash
docker-compose exec db mysql -uapp -papp_secret espace_privatif \
  -e "SELECT variables FROM mail_queue ORDER BY id DESC LIMIT 1\G"
```

### 5. Completer la signature (CSRF requis)

```bash
curl -b cookies.txt -X POST http://localhost:8080/api/documents/1/sign/complete \
     -H "Content-Type: application/json" \
     -H "X-CSRF-Token: $TOKEN" \
     -d '{
       "otp": "123456",
       "signature": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9ZwwQ9wAAAAASUVORK5CYII="
     }'
```

### 6. Refuser un document (CSRF requis)

```bash
curl -b cookies.txt -X POST http://localhost:8080/api/documents/1/refuse \
     -H "Content-Type: application/json" \
     -H "X-CSRF-Token: $TOKEN" \
     -d '{"reason":"Loyer incorrect"}'
```

### 7. Notification de finalisation par SOTHIS

```bash
curl -i -X POST http://localhost:8080/api/sothis/document/finalized \
     -H "Content-Type: application/json" \
     -H "X-Sothis-Key: sothis-shared-key-dev" \
     -d '{
       "document_id": "DOC-2026-0001",
       "pdf_url": "https://sothis.local/files/signed/DOC-2026-0001.pdf"
     }'
```

Codes de reponse :

- `200` : document passe en `signe_valide`, mail final mis en file
- `200` + `idempotent: true` : deja en `signe_valide`, rien refait
- `401` : cle API invalide
- `404` : document inconnu
- `409` : document pas encore en etat `signe`
- `422` : payload invalide

## Tests

```bash
# Lancer toute la suite
docker-compose exec app vendor/bin/phpunit

# Lancer uniquement les tests unitaires
docker-compose exec app vendor/bin/phpunit --testsuite unit
```

Couverture des cas critiques :

| Suite | Fichier | Cas couverts |
|-------|---------|--------------|
| unit | `AuthServiceTest` | login OK, mot de passe faux, email inconnu, compte verrouille, declenchement du lockout |
| unit | `OtpServiceTest` | absence de code, mauvais code, max attempts, code valide, generation + envoi |
| unit | `SignatureServiceTest` | start OK, refus si deja signe, complete avec mauvais OTP, complete avec etat invalide |
| unit | `CsrfTokenManagerTest` | creation stable, validation, rotation |
| unit | `DocumentStateTest` | transitions autorisees, etats terminaux |
| integration | `AuthMiddlewareTest` | acces refuse sans session, acces autorise avec session |
| integration | `CsrfMiddlewareTest` | rejet sans token, acceptation avec header valide, GET non controles |

Aucun test ne depend de la base, on s'appuie uniquement sur des mocks PHPUnit.

## Architecture du code

```
app/
  Controllers/    Endpoints HTTP
                  (Health, Auth, Document, Signature, Sothis)
  Services/       Logique metier
                  (Auth, Document, Signature, Otp, Mail, SothisGateway, Audit)
  Repositories/   Acces BDD via PDO
                  (User, Document, Signature, Otp, Outbox, AuditLog)
  Models/         Objets de domaine (User, Document, DocumentState)
  Middleware/     Session, Auth, JsonBody, SecurityHeaders, Csrf
  Security/       CsrfTokenManager
  Database/       Connection PDO
  Http/           JsonResponse helper

bin/
  ws-server.php    Serveur Ratchet (canal SOTHIS)
  mail-worker.php  Worker batch pour la file mail_queue
  seed-users.php   Seed locataires + documents de demo

config/           settings.php, dependencies.php (DI), middleware.php
routes/           api.php
public/           index.php (entry point Slim)
migrations/       SQL initial + seed tenant/residence
docker/           Dockerfile + nginx
tests/            Unit/ + Integration/ (PHPUnit)
```

## Limites connues (V1)

- Mail reel : transport `null` par defaut, on bascule sur SMTP via `MAIL_DSN`.
- Pas de rate limiting applicatif (a ajouter en middleware si besoin).
- WebSocket sans TLS local (a placer derriere un reverse proxy en prod).
- Endpoint de depot SOTHIS (POST /api/sothis/documents) non encore branche : prochaine iteration.
- CSRF a token de session, pas a usage unique : suffisant pour cette V1.
