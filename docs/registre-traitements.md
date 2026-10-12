# Registre des traitements — MonAvisPro

Responsable : Damien Chauveau, entrepreneur individuel. Contact : contact@monavispro.fr.
Mis à jour le 12 octobre 2026. À relire à chaque nouvelle fonctionnalité ou nouveau prestataire.

## 1. Comptes clients

- **Finalité** : créer et sécuriser l'accès au service.
- **Base légale** : exécution du contrat.
- **Personnes concernées** : commerçants clients.
- **Données** : e-mail, mot de passe haché, date de création, préférences d'alerte.
- **Durée** : tant que le compte existe ; effacement sous 30 jours après une demande de suppression.
- **Destinataires** : Railway (hébergement), prestataire d'e-mails (réinitialisation, alertes).

## 2. Établissements et connexion Google

- **Finalité** : relier la fiche Google du commerce, lire ses avis et publier ses réponses.
- **Base légale** : exécution du contrat.
- **Données** : nom, adresse, identifiants de fiche Google, jetons d'accès Google (chiffrés), réglages de réponse, e-mail de destination du bilan mensuel.
- **Durée** : tant que l'établissement existe. Les jetons sont effacés à la suppression.
- **Destinataires** : Google LLC (API Business Profile), Railway.

## 3. Avis Google des clients du commerçant

- **Finalité** : afficher les avis et aider le commerçant à y répondre.
- **Base légale** : intérêt légitime du commerçant à répondre aux avis publics laissés sur sa fiche.
- **Personnes concernées** : auteurs des avis (clients du commerçant).
- **Données** : nom affiché, photo de profil Google, note, texte, date, réponse du commerçant. Données déjà publiques sur Google Maps.
- **Durée** : tant que l'établissement existe dans MonAvisPro.
- **Destinataires** : OpenAI (texte, note et prénom envoyés pour proposer une réponse ou analyser les thèmes ; pas d'entraînement sur ces données via l'API), Railway.

## 4. Sécurité et journaux

- **Finalité** : prévenir les abus (limiteurs de connexion, d'inscription, d'appels IA) et diagnostiquer les pannes.
- **Base légale** : intérêt légitime.
- **Données** : adresse IP, date, route appelée, messages d'erreur (jamais de mot de passe ni de jeton).
- **Durée** : 30 jours au plus (journaux Railway) ; compteurs des limiteurs : 1 heure au plus.

## 5. E-mails de service

- **Finalité** : réinitialisation de mot de passe, alerte d'avis négatif, rapport hebdomadaire, bilan mensuel.
- **Base légale** : exécution du contrat.
- **Données** : e-mail du destinataire, contenu des avis concernés.
- **Destinataire** : prestataire d'envoi configuré dans `MAILER_DSN`.

## Prestataires hors UE

Google LLC, OpenAI, Railway Corporation : États-Unis. Transferts encadrés par le cadre de protection des données UE–États-Unis ou des clauses contractuelles types, selon le prestataire.

## Sauvegardes

Base de données sauvegardée par Railway, conservation de 30 jours au plus (à régler dans Railway → Postgres → Backups). Une donnée supprimée disparaît des sauvegardes dans ce délai.

## En cas de fuite de données

1. Couper l'accès concerné : changer `APP_SECRET`, révoquer le client OAuth dans Google Cloud, régénérer la clé OpenAI. Ne **pas** changer `APP_ENCRYPTION_KEY` sans plan de rechiffrement.
2. Noter dans ce fichier : date, données touchées, nombre de personnes, mesures prises.
3. Si un risque existe pour les personnes : déclaration à la CNIL sous **72 heures** (notifications.cnil.fr).
4. Si le risque est élevé : prévenir les clients concernés par e-mail.

## Mesures de sécurité en place

Mots de passe hachés, jetons Google chiffrés (libsodium), HTTPS et HSTS, en-têtes CSP, contrôle d'accès par établissement, limiteurs de débit, CSRF, sauvegardes, accès Railway et Google Cloud protégés par double authentification (à vérifier dans tes comptes).
