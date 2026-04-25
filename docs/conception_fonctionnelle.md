# Conception fonctionnelle ESPACE-PRIVATIF

## 1. Contexte

ESPACE-PRIVATIF est un portail web destiné aux locataires. Il permet de consulter et signer électroniquement les documents générés par le logiciel métier SOTHIS (baux, avenants, états des lieux, quittances, etc.).

SOTHIS reste la source de vérité métier. ESPACE-PRIVATIF est uniquement un canal d'interaction avec le locataire.

## 2. Acteurs

- Locataire : consulte et signe les documents.
- Responsable de résidence : reçoit une notification après signature.
- SOTHIS : génère les documents, met à jour la base, valide la signature.
- Administrateur technique : supervision, logs, support.

## 3. Fonctionnalités principales

### 3.1 Authentification
- Connexion par email et mot de passe.
- Lien magique envoyé par mail comme alternative (token signé, durée 30 min).
- Verrouillage temporaire après 5 échecs.
- Session courte (2h), régénération du token CSRF par formulaire.

### 3.2 Consultation des documents
- Liste filtrée par état (à signer, signé, validé, archivé).
- Aperçu PDF intégré (PDF.js).
- Téléchargement uniquement après validation finale par SOTHIS.

### 3.3 Signature électronique
- Capture d'une signature manuscrite via canvas HTML5 (souris, doigt, stylet).
- Saisie d'un code OTP envoyé par mail pour confirmer (preuve de consentement).
- Stockage de la signature au format PNG base64 plus métadonnées.
- Mise à jour de l'état du document.

### 3.4 Notifications
- Mail au locataire : confirmation de signature.
- Mail au responsable de résidence.
- Mail au locataire après validation finale par SOTHIS avec PDF signé.

### 3.5 Communication SOTHIS
- Envoi des données de signature via WebSocket sécurisé.
- Réception du PDF signé final via le même canal ou via webhook HTTPS.

## 4. Cycle de vie d'un document

| État | Déclencheur | Action |
|------|-------------|--------|
| en_attente_signature | Dépôt SOTHIS | Mail au locataire avec lien |
| signature_en_cours | Locataire ouvre la signature | Verrouillage du document |
| signe | Signature validée par OTP | Mail locataire et responsable, push WebSocket SOTHIS |
| signe_valide | SOTHIS retourne le PDF final | Document final accessible au locataire |
| refuse | Locataire refuse | Mail SOTHIS, document archivé |
| expire | Délai dépassé sans action | Notification SOTHIS |

Transitions strictes. Toute transition est journalisée.

## 5. Gestion des erreurs

- Erreur de connexion SOTHIS : mise en file d'attente, retry exponentiel (1 min, 5 min, 30 min, 1 h). Au bout de 3 échecs, alerte admin.
- Document introuvable : 404 personnalisée, log de la tentative.
- Token expiré : redirection vers la page de demande d'un nouveau lien.
- OTP invalide : 3 essais maximum, puis nouvelle demande obligatoire.
- Erreur de signature côté serveur : rollback de l'état, conservation du brouillon de signature 24 h.

Toutes les erreurs côté utilisateur sont génériques. Le détail technique reste dans les logs.

## 6. Règles de sécurité fonctionnelle

- Un locataire ne voit que ses propres documents (filtrage tenant + locataire).
- Un document ne peut être signé qu'une seule fois.
- La signature engage uniquement la personne authentifiée à ce moment.
- Toute action critique est tracée (audit log immuable).
- Les liens de signature contiennent un token unique non devinable (32 octets aléatoires).

## 7. RGPD

- Mention d'information affichée avant la signature.
- Consentement explicite enregistré (case à cocher plus OTP).
- Conservation des signatures et logs : durée légale du contrat plus 5 ans.
- Droit d'accès et de rectification via le responsable de résidence.
- Données minimisées : pas de donnée hors usage métier.
- Hébergement en France ou UE.

## 8. Multi-tenant

Chaque client SOTHIS correspond à un tenant. Toutes les tables métier portent une colonne tenant_id. Les requêtes passent par un repository qui injecte automatiquement le filtre tenant courant. Aucun accès croisé n'est possible.

## 9. Hors périmètre

- Génération du document Word ou PDF initial (rôle de SOTHIS).
- Gestion comptable, facturation.
- Interface d'administration SOTHIS.
- Application mobile native.
