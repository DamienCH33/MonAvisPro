# MonAvisPro

![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=flat-square&logo=php&logoColor=white)
![Symfony](https://img.shields.io/badge/Symfony-7.0-000000?style=flat-square&logo=symfony&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-4169E1?style=flat-square&logo=postgresql&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?style=flat-square&logo=docker&logoColor=white)
![JWT](https://img.shields.io/badge/Auth-JWT-000000?style=flat-square&logo=jsonwebtokens&logoColor=white)
![PHPStan](https://img.shields.io/badge/PHPStan-Level%207-2D1B4E?style=flat-square)
![Tests](https://img.shields.io/badge/Tests-PHPUnit-3776AB?style=flat-square&logo=php&logoColor=white)
![PHP CS Fixer](https://img.shields.io/badge/Code%20Style-PHP%20CS%20Fixer-8892BF?style=flat-square)
![CI](https://img.shields.io/badge/CI-GitHub%20Actions-2088FF?style=flat-square&logo=githubactions&logoColor=white)
![License](https://img.shields.io/badge/License-Proprietary-red?style=flat-square)
![Status](https://img.shields.io/badge/Status-Production-success?style=flat-square)

> Tableau de bord de gestion d'avis Google pour TPE et indépendants — surveillance automatique, alertes email et réponses IA en 1 clic.

![Dashboard MonAvisPro](https://via.placeholder.com/1200x600/0a0e1a/5dcaa5?text=Dashboard+Screenshot)

---

## Le problème résolu

Les commerçants reçoivent des avis Google tous les jours mais découvrent souvent un avis négatif des semaines après qu'il a été posté — sans jamais y avoir répondu. Les outils existants (BrightLocal, EmbedSocial) coûtent 50 à 300€/mois, hors budget pour un restaurateur ou un artisan.

MonAvisPro comble ce gap : un tableau de bord simple, en français, pensé pour un gérant qui consacre 5 minutes par semaine à sa réputation en ligne.

---

## Démo live

🔗 **[monavispro-production.up.railway.app](https://monavispro-production.up.railway.app)**

Compte de démo : `demo@monavispro.fr` / `demo1234`

---

## Fonctionnalités

- **Surveillance automatique** — synchronisation des avis Google toutes les 6h via Symfony Scheduler
- **Alertes email immédiates** — notification dès qu'un avis ≤ 2★ est détecté
- **Analyse thématique IA** — extraction des thèmes récurrents dans les avis positifs et négatifs
- **Génération de réponses** — personnalisée par établissement (vouvoiement/tutoiement, ton, signature, consignes), relue avant publication, via OpenAI GPT-4o-mini
- **Connexion Google Business Profile** — synchronisation de **tous** les avis d'une fiche et publication des réponses directement sur Google (OAuth 2.0, jetons chiffrés)
- **Boîte « À traiter »** — tous les avis sans réponse, tous établissements confondus, les négatifs en premier
- **Bilan mensuel au commerçant** — e-mail automatique de suivi (avis, note, réponses)
- **Dashboard interactif** — courbe d'évolution de la note, répartition par étoile, filtres (note, période, à répondre/répondu) et pagination
- **API REST complète** — authentification JWT, endpoints sécurisés par ownership, limitation de débit

---

## Stack technique

| Couche           | Technologie                                  |
|------------------|----------------------------------------------|
| Backend          | Symfony 7, PHP 8.4                           |
| Base de données  | PostgreSQL 16, Doctrine ORM                  |
| Auth             | JWT — LexikJWTAuthenticationBundle           |
| IA               | OpenAI GPT-4o-mini via HttpClient Symfony    |
| Avis Google      | Google Business Profile API (OAuth) + Places |
| Email            | Symfony Mailer (expéditeur configurable)     |
| Scheduler        | Symfony Scheduler                            |
| Frontend         | Twig, Bootstrap 5, Chart.js                  |
| Environnement    | Docker + Docker Compose                      |
| Déploiement      | Railway.app + FrankenPHP                     |

### Choix techniques principaux

- **Symfony 7 + PHP 8.4** pour bénéficier des typages stricts et des dernières fonctionnalités du langage
- **PostgreSQL** plutôt que MySQL pour la robustesse des types JSON et la prise en charge native des fonctions analytiques (`TO_CHAR`, fenêtrage)
- **JWT (Lexik)** pour l'authentification API stateless, complétée par une session Symfony pour les pages Twig authentifiées
- **Symfony Scheduler** plutôt que cron Linux pour garder l'orchestration dans le code versionné et testable
- **Railway + FrankenPHP** pour un déploiement reproductible et un démarrage rapide

---

## Qualité de code et CI

Le projet est intégré dans une pipeline GitHub Actions exécutée à chaque `push` et `pull_request` sur `main` et `develop` :

- **PHPUnit** — tests unitaires et fonctionnels sur une base PostgreSQL réelle (service `postgres:15-alpine` provisionné dans la CI)
- **PHPStan niveau 7** — analyse statique avec l'extension Doctrine pour valider les types et les requêtes ORM
- **PHP CS Fixer** — vérification du style de code (mode `--dry-run` dans la CI, échec en cas de non-conformité)

L'environnement de test reproduit la production (PostgreSQL, JWT, variables d'environnement) pour éviter les écarts de comportement.

---

## Sécurité

Pensé pour héberger les fiches Google de vrais clients :

- **Secrets hors du dépôt** — les clés JWT sont générées au démarrage du conteneur ; les jetons OAuth Google sont **chiffrés en base** (libsodium `secretbox`)
- **En-têtes HTTP de sécurité** sur toutes les réponses — CSP, `X-Frame-Options: DENY`, HSTS, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`
- **Limitation de débit** — connexion (5 / 15 min + throttling du pare-feu), inscription (3 / h), génération IA (60 / h, 10 / h pour la démo)
- **Cloisonnement par propriétaire** — chaque accès à un établissement ou un avis passe par un Voter ; routes limitées aux UUID
- **Anti-XSS** — le contenu des avis (fourni par Google) est systématiquement échappé côté client, les photos de profil restreintes au `https`
- **CSRF** sur les formulaires sensibles, **state OAuth** comparé en temps constant
- **Pas d'énumération de comptes** — message neutre à l'inscription, inscription publique fermée par défaut (`APP_REGISTRATION_ENABLED`)
- **Compte démo en lecture seule** — ni création, ni suppression, ni connexion Google

---

## Refactoring en cours

Suite à un retour de la communauté PHP, j'ai entamé une refactorisation progressive du projet vers une architecture plus modulaire :

- ✅ Introduction de DTO sur les endpoints API (Review, Establishment) pour découpler le contrat d'API de l'entité Doctrine
- 🔄 Extraction de la logique métier des controllers vers des Use Cases dédiés
- 📅 Séparation en Bounded Contexts (Reviews, Establishments, Auth)
- 📅 Mise en place de Ports & Adapters pour isoler le domaine des dépendances externes (Google API, OpenAI, Mailer)

Le code reflète une démarche d'apprentissage continue : chaque itération améliore la testabilité et la maintenabilité.

---

## Installation locale

```bash
# 1. Cloner le projet
git clone https://github.com/DamienCH33/MonAvisPro.git && cd MonAvisPro

# 2. Lancer l'environnement Docker
docker compose up -d

# 3. Installer les dépendances et migrer la base
composer install
php bin/console doctrine:migrations:migrate --no-interaction

# 4. Générer les clés JWT
php bin/console lexik:jwt:generate-keypair

# 5. Charger les données de démo (compte demo@monavispro.fr / demo1234)
php bin/console doctrine:fixtures:load
```

### Variables d'environnement

Copier `.env` vers `.env.local` et renseigner les vraies valeurs :

```env
DATABASE_URL=postgresql://app:secret@postgres:5432/monavispro
JWT_PASSPHRASE=une_phrase_secrete_robuste        # les clés sont générées au 1er démarrage
GOOGLE_PLACES_API_KEY=AIza...
OPENAI_API_KEY=sk-...
# Connexion Google Business Profile (avis + réponses)
GOOGLE_OAUTH_CLIENT_ID=...
GOOGLE_OAUTH_CLIENT_SECRET=...
GOOGLE_OAUTH_REDIRECT_URI=https://votre-domaine/google/callback
MAILER_DSN=smtp://user:pass@sandbox.smtp.mailtrap.io:2525
MAILER_FROM="MonAvisPro <noreply@votre-domaine>"
# Chiffrement des jetons Google : 32 octets en base64 (sinon dérivé d'APP_SECRET)
APP_ENCRYPTION_KEY=                                # openssl rand -base64 32
APP_REGISTRATION_ENABLED=0                         # 1 pour ouvrir l'inscription
TRUSTED_PROXIES=REMOTE_ADDR                        # derrière le proxy Railway
```

> **Déploiement (Railway) :** après fusion, ajouter `APP_ENCRYPTION_KEY` (`openssl rand -base64 32`) et `TRUSTED_PROXIES=REMOTE_ADDR`. Activer la *Google My Business API* (avis) dans Google Cloud, en plus des APIs Business Information et Account Management.

---

## Architecture

```
src/
├── Controller/
│   ├── Api/
│   │   ├── AuthController.php          ← Register, login, /api/me
│   │   ├── EstablishmentController.php ← CRUD + sync manuelle
│   │   ├── ReviewController.php        ← Liste, stats, mark as read
│   │   └── AnalysisController.php      ← Analyse LLM + génération réponse
│   ├── DashboardController.php         ← Pages Twig authentifiées
│   ├── HomeController.php              ← Landing page publique
│   └── SecurityController.php          ← Login/logout session
├── Dto/
│   └── ReviewDTO.php                   ← Contrat d'API découplé de l'entité
├── Entity/
│   ├── User.php
│   ├── Establishment.php
│   ├── Review.php
│   └── ReviewAnalysis.php
├── Service/
│   ├── GooglePlacesService.php         ← HttpClient → API Google Places
│   ├── ReviewSyncService.php           ← Orchestration sync + alertes
│   ├── LlmService.php                  ← HttpClient → OpenAI
│   ├── ReviewAnalysisService.php       ← Analyse thématique
│   └── AlertEmailService.php           ← Mailer alertes négatives
├── Security/
│   ├── Voter/EstablishmentVoter.php    ← Cloisonnement par propriétaire
│   └── DemoAccountGuard.php            ← Restrictions du compte démo
├── EventSubscriber/
│   └── SecurityHeadersSubscriber.php   ← En-têtes HTTP de sécurité
├── Service/
│   ├── GoogleBusinessProfileService.php ← OAuth + avis/réponses (API v4)
│   ├── GoogleTokenManager.php          ← Jetons Google chiffrés + refresh
│   └── TokenCipher.php                 ← Chiffrement libsodium
└── Scheduler/
    ├── SyncReviewsTask.php             ← Sync automatique toutes les 6h
    ├── WeeklyReportTask.php            ← Rapport interne du lundi
    └── MonthlyClientReportTask.php     ← Bilan mensuel au commerçant
```

---

## API REST

### Authentification

| Méthode | Route                  | Description                  |
|---------|------------------------|------------------------------|
| POST    | `/api/auth/register`   | Créer un compte              |
| POST    | `/api/auth/login`      | Obtenir un JWT               |
| GET     | `/api/me`              | Profil utilisateur connecté  |

### Établissements

| Méthode | Route                                | Description                  |
|---------|--------------------------------------|------------------------------|
| GET     | `/api/establishments`                | Lister ses établissements    |
| POST    | `/api/establishments`                | Ajouter un établissement     |
| GET     | `/api/establishments/{id}`           | Détail                       |
| PATCH   | `/api/establishments/{id}`           | Modifier                     |
| DELETE  | `/api/establishments/{id}`           | Supprimer                    |
| POST    | `/api/establishments/{id}/sync`      | Sync manuelle                |

### Avis

| Méthode | Route                                          | Description                       |
|---------|------------------------------------------------|-----------------------------------|
| GET     | `/api/establishments/{id}/reviews`             | Liste avec filtres + pagination   |
| GET     | `/api/establishments/{id}/reviews/stats`       | Stats + courbe                    |
| PATCH   | `/api/reviews/{id}/read`                       | Marquer comme lu                  |
| POST    | `/api/reviews/{id}/generate-reply`             | Générer une réponse IA            |

### Analyse

| Méthode | Route                                              | Description           |
|---------|----------------------------------------------------|-----------------------|
| GET     | `/api/establishments/{id}/analysis`                | Récupérer l'analyse   |
| POST    | `/api/establishments/{id}/analysis/refresh`        | Relancer l'analyse    |

---

## Lancer les tests en local

```bash
# Tests unitaires et fonctionnels
php bin/phpunit

# Analyse statique
vendor/bin/phpstan analyse src

# Vérification du style de code
vendor/bin/php-cs-fixer fix --dry-run --diff
```

---

## Licence

**Proprietary — All rights reserved.**

Ce code est rendu public à des fins de démonstration de compétences techniques. Toute réutilisation, modification, redistribution ou exploitation commerciale est interdite sans accord préalable écrit de l'auteur.

© 2026 Damien Chauveau
