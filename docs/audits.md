# Audits MonAvisPro

Même grille que Cap Monta : sécurité (tests d'intrusion), back et API, base de données, IA, RGPD, performance, SEO, accessibilité, exploitation.

---

## Audit d'octobre 2026 (branche `fix/audit-octobre`)

Bilan : **2 bloquants** avant de vrais clients, **8 importants**, une douzaine de moindres. Tous les points de code importants sont corrigés sur la branche. Il reste des actions dans tes comptes (Google, Railway) et des chantiers plus longs.

### Bloquants avant le premier client

| # | Constat | Statut |
|---|---|---|
| B1 | **Google en mode « Test »** : 100 utilisateurs au maximum, et chaque client doit se reconnecter tous les 7 jours (Google fait expirer les jetons des applis en Test). Le passage en Production exige un nom de domaine à toi. | À faire : acheter `monavispro.fr`, le brancher sur Railway, remplir Branding, publier. |
| B2 | **Pas de suppression de compte**, alors que la politique de confidentialité la promet. | Prévu dans `feature/ux-et-compte` (maquette à valider). |

### Importants

| # | Domaine | Constat | Statut |
|---|---|---|---|
| I1 | Sécurité | Le formulaire d'inscription affichait « Cet email est déjà utilisé » (on pouvait tester qui a un compte), et n'avait **aucune limite** (l'API en avait une). | **Corrigé** : message neutre, 3 créations par heure et par IP. Test fonctionnel ajouté. |
| I2 | Sécurité | Limiteurs de débit peut-être **contournables avec un faux `X-Forwarded-For`** (même faille que Cap Monta). Dépend de la façon dont Railway remplit cet en-tête : invérifiable sans la prod. | **À tester** avec `bin/audit-prod.sh`. Si KO, je corrige. |
| I3 | API / coûts | La **synchro manuelle** n'avait ni limite ni blocage démo : n'importe qui pouvait épuiser le quota Google (300 requêtes/minute **partagées par tous les clients**) et faire payer l'API Places. | **Corrigé** : 6 synchros par heure et par établissement ; sur le compte démo, aucune requête à Google. Test ajouté. |
| I4 | IA | L'analyse envoyait **tous les avis** à OpenAI (coût, et échec au-delà de la taille maximale), sans délai maximum, et les erreurs n'étaient pas journalisées. | **Corrigé** : 200 derniers avis, 30 s maximum, erreurs dans les logs, consigne anti-injection ajoutée au prompt d'analyse. |
| I5 | Performance | Le tableau de bord **chargeait tous les avis en mémoire** à chaque affichage pour calculer les chiffres en PHP. Lent dès quelques milliers d'avis. | **Corrigé** : calcul en SQL (vérifié sur Postgres 16 : chiffres identiques). |
| I6 | Sécurité | Mot de passe oublié : si l'envoi d'e-mail échouait, erreur 500 **uniquement pour les comptes existants** (révélait qui a un compte). Objet de l'e-mail en anglais. | **Corrigé** : même page dans tous les cas, erreur dans les logs, objet en français. |
| I7 | Exploitation | Pas de `php.ini` de production dans l'image : erreurs PHP potentiellement affichées aux visiteurs, version de PHP exposée. | **Corrigé** dans le `Dockerfile`. À vérifier au premier déploiement (logs de build Railway). |
| I8 | Back | E-mails non normalisés : `Jean@Mail.fr` et `jean@mail.fr` donnaient deux comptes, et la connexion échouait selon les majuscules. | **Corrigé** : e-mail en minuscules à l'enregistrement, recherche insensible à la casse (comptes existants compris, sans migration). Tests ajoutés. |

### Moindres

| # | Domaine | Constat | Statut |
|---|---|---|---|
| M1 | Sécurité | Connexion API : réponse plus rapide si le compte n'existe pas (on pouvait chronométrer). | **Corrigé** : hachage factice. |
| M2 | Performance | Pas de compression ni de cache navigateur sur CSS/JS, alors qu'ils sont versionnés. | **Corrigé** dans le `Caddyfile`. |
| M3 | SEO | Pas de `robots.txt`, pas d'aperçu de partage (WhatsApp, LinkedIn), pages de mot de passe oublié indexables. | **Corrigé**. Pas de sitemap ni de balise canonique tant qu'il n'y a pas de nom de domaine. |
| M4 | RGPD | Politique : citait un cookie « rester connecté » inexistant, oubliait la photo de profil des auteurs, la durée des journaux et des sauvegardes. | **Corrigé**. À toi : régler les sauvegardes Railway sur 30 jours maximum. |
| M5 | RGPD | Pas de registre des traitements. | **Ajouté** : `docs/registre-traitements.md`. |
| M6 | Sécurité | Historique Git : clés JWT et `APP_SECRET` de dev (`.env.dev`, `config/jwt/private.pem`). Sans risque si la prod utilise d'autres valeurs (les clés JWT sont régénérées à chaque déploiement). | **À vérifier** : la variable `APP_SECRET` sur Railway ne doit pas commencer par `c8d1`. Sinon, en mettre une nouvelle. |
| M7 | Base | Pas de contrainte `CHECK` sur la note (1 à 5), pas de `ON DELETE CASCADE` : supprimer un établissement charge et supprime ses avis un par un, et supprimer un utilisateur échoue s'il a une demande de mot de passe en cours. | À traiter avec la suppression de compte (migration). |
| M8 | Google | Supprimer un établissement ne **révoque pas l'accès** côté Google. | À traiter avec la suppression de compte. |
| M9 | Exploitation | Dépendances de développement installées en prod (`composer install` sans `--no-dev`). | Plus tard : vérifier d'abord que la démo n'en dépend pas. |
| M10 | Accessibilité | Pas de lien « Aller au contenu » ; confirmations et erreurs via les fenêtres natives `alert()` / `confirm()`. Les formulaires ont bien leurs étiquettes, et la note n'est jamais donnée par la couleur seule. | Avec la branche UX. |
| M11 | Démo | Compte démo partagé : un visiteur peut modifier la signature, visible par les autres jusqu'à la remise à zéro de la nuit. | Accepté (aucun risque XSS, la remise à zéro corrige). |
| M12 | Tests | Tests fonctionnels absents pour À traiter, Réglages, la démo. | En cours (synchro démo couverte). |

### Vérifié et conforme

- **Accès aux données d'un autre client** : toutes les routes API passent par le voter `ESTABLISHMENT_OWNER`, toutes les pages du tableau de bord vérifient le propriétaire (404 sinon).
- **XSS** : Twig échappe tout ; le JS qui construit du HTML passe chaque donnée par `esc()`.
- **CSRF** : formulaire de connexion, déconnexion, actions sensibles.
- **Mot de passe oublié** : aucun e-mail envoyé à une adresse inconnue (la faille trouvée sur Cap Monta n'existe pas ici).
- **IA** : relecture humaine obligatoire avant publication, consigne anti-injection, aucun fait inventé demandé, plafonds d'usage (60/h, 10/h en démo).
- **Erreurs API** : message générique, jamais de trace.
- **Synchro planifiée** : une fiche en erreur ne bloque pas les autres, et l'erreur est journalisée.
- **Migrations** : réversibles (méthodes `down()` présentes).
- **Secrets Google et OpenAI** : jamais passés dans l'historique Git.

### Ce qui a été réellement exécuté

- PHPStan niveau 7 : **0 erreur**.
- Tests unitaires : **21/21**.
- Requêtes SQL du tableau de bord et recherche d'e-mail : exécutées sur **Postgres 16** avec un jeu d'essai (5 avis).
- **Non exécutés ici** : les tests fonctionnels (Doctrine exige PHP 8.4, le poste d'audit a PHP 8.3) et le build Docker. À lancer en local avant de merger :

```bash
php bin/phpunit && vendor/bin/phpstan analyse
bin/audit-prod.sh   # après déploiement
```

---

## Audits précédents

- **Sécurité, PR #1** : jetons Google chiffrés, IDOR, XSS stocké, CSRF, limiteurs, énumération de comptes à l'API, en-têtes, clés JWT retirées du dépôt.
- **UX et design, PR #2** : refonte « sombre retravaillé », réponse depuis « À traiter », démo crédible.
