# Sécurité

## 1. Authentification

- Mot de passe hashé via password_hash (bcrypt cost 12) ou Argon2id si dispo.
- Lien magique par mail : token aléatoire 32 octets, hashé en BDD, durée 30 min, usage unique.
- Verrouillage progressif : 5 échecs entraînent un blocage de 15 minutes.
- Sessions PHP : cookie HttpOnly, Secure, SameSite=Lax, durée 2h.
- Régénération de l'identifiant de session à chaque login.
- Pas d'exposition d'un id technique dans les URL accessibles au client.

## 2. Autorisation

- Middleware d'autorisation : un locataire ne peut accéder qu'à ses propres documents.
- Vérification systématique de tenant_id ET user_id côté serveur sur chaque action.
- Aucun contrôle uniquement côté front.

## 3. Protection des documents

- PDF stockés hors webroot, servis par un contrôleur PHP qui vérifie les droits.
- URL de téléchargement signées à durée limitée (5 min).
- Empreinte SHA-256 du PDF d'origine vérifiée à chaque service.
- Aucune URL publique vers les fichiers physiques.

## 4. Signature électronique

Niveau visé : signature électronique simple selon le règlement eIDAS. Suffisant pour le cadre habituel locataire/agence. Si le besoin évolue vers une signature avancée ou qualifiée, prévoir un prestataire certifié.

Preuves collectées :

- Identité authentifiée (id user, tenant).
- Horodatage serveur précis (DATETIME(3)).
- IP et user agent.
- OTP validé (méthode et horodatage).
- Empreinte SHA-256 du PDF signé et de l'image de signature.
- Hash chaîné dans audit_log (chaque ligne contient le hash de la précédente).

L'ensemble forme un dossier de preuve exportable au format JSON signé.

## 5. WebSocket

- TLS obligatoire (wss://).
- Jeton signé HS256 vérifié à la connexion (clé partagée par tenant côté SOTHIS).
- Vérification du tenant sur chaque message reçu (le tenant déclaré doit correspondre au jeton).
- Idempotence par message_id, déduplication côté SOTHIS et côté ESPACE-PRIVATIF.

## 6. Protection contre les attaques courantes

| Risque | Mitigation |
|--------|-----------|
| XSS | Échappement Twig par défaut, CSP stricte (default-src 'self'). |
| CSRF | Token par formulaire, vérifié serveur. SameSite=Lax. |
| SQLi | PDO prepared statements, jamais de concaténation. |
| Brute force login | Rate limiting plus verrouillage progressif. |
| Brute force OTP | 3 tentatives max, code à 6 chiffres, durée 5 min. |
| IDOR | Filtrage tenant_id et user_id sur chaque requête. |
| Clickjacking | Header X-Frame-Options: DENY. |
| MITM | HSTS activé, certificat valide. |
| Upload malveillant | Pas d'upload utilisateur. Seules les signatures (canvas) sont reçues, validées comme PNG. |

## 7. Gestion des secrets

- Stockés en variables d'environnement, hors du dépôt.
- Rotation des clés WebSocket SOTHIS prévue (champ sothis_api_key_hash, table tenants).
- Aucun secret écrit dans les logs.

## 8. RGPD

- Donnée minimisée : nom, prénom, email, téléphone, signature, logs techniques.
- Information du locataire avant signature.
- Consentement explicite (case à cocher plus OTP).
- Durée de conservation : durée du contrat plus 5 ans pour les preuves de signature.
- Droit d'accès et de suppression géré par le responsable du traitement (le bailleur).
- Hébergement UE.
- Registre de traitement maintenu hors application.

## 9. Journalisation

- Logs applicatifs anonymisés (pas d'email en clair dans les logs INFO).
- audit_log immuable, hash chaîné.
- Conservation logs techniques : 90 jours. Logs d'audit : durée légale.
