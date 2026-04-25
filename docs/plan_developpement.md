# Plan de développement 4 à 5 jours

## Principe

Livrer un parcours fonctionnel complet en bout de chaîne dès le jour 2. Renforcer ensuite. La signature et l'intégration SOTHIS sont prioritaires. Tout le reste est secondaire.

## Jour 1 : socle technique

Matin
- Installation Docker (php-fpm, nginx, mysql, ws-server).
- Squelette Slim 4, configuration Twig, PDO, Monolog.
- Migrations initiales (tenants, users, documents, signatures, audit_log).
- Seed minimal (1 tenant, 1 résidence, 2 locataires de test, 2 documents).

Après-midi
- Authentification email plus mot de passe.
- Middleware tenant et auth.
- Page liste des documents.
- Affichage PDF via PDF.js.

Critère de sortie : un locataire peut se connecter et voir ses documents en attente.

## Jour 2 : signature électronique

Matin
- Page de signature : intégration signature_pad sur canvas.
- Endpoint de pré-signature (vérifie l'état, prépare l'OTP, envoie le mail).
- Génération et envoi OTP par mail.

Après-midi
- Endpoint de validation : vérifie OTP, enregistre la signature, change l'état du document, écrit l'audit.
- Mails de confirmation (locataire, responsable de résidence).
- Tests manuels de bout en bout.

Critère de sortie : un document peut passer en état "signé" avec preuves complètes.

## Jour 3 : intégration SOTHIS

Matin
- Endpoint REST de dépôt SOTHIS (POST /api/sothis/documents).
- Authentification du dépôt par jeton signé.
- Persistance et déclenchement du mail au locataire.

Après-midi
- Serveur Ratchet (bin/ws-server.php).
- Émission signature.completed via outbox plus worker.
- Réception document.finalized, mise à jour de l'état signe_valide, stockage du PDF final.
- Mail final avec lien de téléchargement.

Critère de sortie : cycle complet SOTHIS -> ESPACE-PRIVATIF -> signature -> SOTHIS -> PDF final.

## Jour 4 : sécurité, robustesse, finitions

Matin
- Rate limiting login et OTP.
- CSRF, CSP, HSTS, headers de sécurité.
- Audit log avec hash chaîné, vérification d'intégrité.
- Gestion des refus et expirations.

Après-midi
- Magic links de connexion.
- Téléchargement sécurisé (URL signée).
- Tests PHPUnit sur la machine d'état documents et la signature.
- Documentation README technique.

Critère de sortie : application robuste, sécurité de base validée.

## Jour 5 : tampon et démonstration

- Corrections de bugs.
- Améliorations UI mineures (responsive, accessibilité de base).
- Jeu de données de démo.
- Préparation de la présentation : parcours commenté, schémas, points forts.
- Si temps disponible : page admin minimale (consultation logs, état file outbox).

## Priorisation des fonctionnalités

| Priorité | Fonctionnalité |
|----------|----------------|
| Indispensable | Connexion, liste documents, signature OTP, mails, WebSocket |
| Important | Magic link, audit log chaîné, retry outbox |
| Confort | Refus motivé, suivi statut côté locataire, page admin |
| Bonus | Signature multi-champs avancée, export du dossier de preuve |

## Risques principaux et parades

- Intégration WebSocket SOTHIS non testable réellement : prévoir un mock SOTHIS qui répond aux messages, scriptable.
- Délai SMTP : fournir un transport "log" en dev pour ne pas dépendre d'un vrai serveur.
- Génération PDF signé hors périmètre : SOTHIS s'en charge, ne pas tenter de la coder côté ESPACE-PRIVATIF.
