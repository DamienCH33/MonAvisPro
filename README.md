# MonAvisPro

![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?style=flat-square&logo=php&logoColor=white)
![Symfony](https://img.shields.io/badge/Symfony-7.0-000000?style=flat-square&logo=symfony&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-4169E1?style=flat-square&logo=postgresql&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?style=flat-square&logo=docker&logoColor=white)
![JWT](https://img.shields.io/badge/Auth-JWT-000000?style=flat-square&logo=jsonwebtokens&logoColor=white)

> Tableau de bord de gestion d'avis Google pour TPE et indépendants — surveillance automatique, alertes email et réponses IA en 1 clic.

![Dashboard MonAvisPro](https://via.placeholder.com/1200x600/0a0e1a/5dcaa5?text=Dashboard+Screenshot)

---

## Le problème résolu

Les commerçants reçoivent des avis Google tous les jours mais découvrent souvent un avis négatif des semaines après qu'il a été posté — sans jamais y avoir répondu. Les outils existants (BrightLocal, EmbedSocial) coûtent 50 à 300€/mois, hors budget pour un restaurateur ou un artisan. MonAvisPro comble ce gap : un tableau de bord simple, en français, pensé pour un gérant qui consacre 5 minutes par semaine à sa réputation en ligne.

---

## Démo live

🔗 **monavispro-production.up.railway.app

Compte de démo : `demo@monavispro.fr` / `demo1234`

---

## Fonctionnalités

- **Surveillance automatique** — synchronisation des avis Google toutes les 6h via Symfony Scheduler
- **Alertes email immédiates** — notification dès qu'un avis ≤ 2★ est détecté
- **Analyse thématique IA** — extraction des thèmes récurrents dans les avis positifs et négatifs
- **Génération de réponses** — 3 tons disponibles (cordial, formel, empathique) via OpenAI GPT-4o-mini
- **Dashboard interactif** — courbe d'évolution de la note, répartition par étoile, filtres et pagination
- **API REST complète** — authentification JWT, endpoints sécurisés par ownership

---

## Stack technique

| Couche          | Technologie                               |
| --------------- | ----------------------------------------- |
| Backend         | Symfony 7, PHP 8.4                        |
| Base de données | PostgreSQL 16, Doctrine ORM               |
| Auth            | JWT — LexikJWTAuthenticationBundle        |
| IA              | OpenAI GPT-4o-mini via HttpClient Symfony |
| Avis Google     | Google Places API (New)                   |
| Email           | Symfony Mailer + Mailtrap                 |
| Scheduler       | Symfony Scheduler                         |
| Frontend        | Twig, Bootstrap 5, Chart.js               |
| Environnement   | Docker + Docker Compose                   |
| Déploiement     | Railway.app                               |

---

## Installation locale

```bash
# 1. Cloner le projet
git clone https://github.com/ton-username/monavispro.git && cd monavispro

# 2. Lancer l'environnement Docker
docker compose up -d

# 3. Installer les dépendances et migrer la base
composer install && php bin/console doctrine:migrations:migrate --no-interaction
```

### Variables d'environnement requises

Crée un fichier `.env.local` à la racine :

```env
DATABASE_URL=postgresql://app:secret@postgres:5432/monavispro
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your_passphrase
GOOGLE_PLACES_API_KEY=AIza...
OPENAI_API_KEY=sk-...
MAILER_DSN=smtp://user:pass@sandbox.smtp.mailtrap.io:2525
```

### Générer les clés JWT

```bash
php bin/console lexik:jwt:generate-keypair
```

### Charger les données de test

```bash
php bin/console doctrine:fixtures:load
```

Compte demo disponible : `demo@monavispro.fr` / `demo1234`

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
│   └── SecurityController.php         ← Login/logout session
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
└── Scheduler/
    ├── SyncReviewsTask.php             ← Sync automatique toutes les 6h
    └── WeeklyReportTask.php            ← Rapport lundi 8h
```

---

## API REST

### Authentification

| Méthode | Route                | Description                 |
| ------- | -------------------- | --------------------------- |
| POST    | `/api/auth/register` | Créer un compte             |
| POST    | `/api/auth/login`    | Obtenir un JWT              |
| GET     | `/api/me`            | Profil utilisateur connecté |

### Établissements

| Méthode | Route                           | Description               |
| ------- | ------------------------------- | ------------------------- |
| GET     | `/api/establishments`           | Lister ses établissements |
| POST    | `/api/establishments`           | Ajouter un établissement  |
| GET     | `/api/establishments/{id}`      | Détail                    |
| PATCH   | `/api/establishments/{id}`      | Modifier                  |
| DELETE  | `/api/establishments/{id}`      | Supprimer                 |
| POST    | `/api/establishments/{id}/sync` | Sync manuelle             |

### Avis

| Méthode | Route                                    | Description                     |
| ------- | ---------------------------------------- | ------------------------------- |
| GET     | `/api/establishments/{id}/reviews`       | Liste avec filtres + pagination |
| GET     | `/api/establishments/{id}/reviews/stats` | Stats + courbe                  |
| PATCH   | `/api/reviews/{id}/read`                 | Marquer comme lu                |
| POST    | `/api/reviews/{id}/generate-reply`       | Générer une réponse IA          |

### Analyse

| Méthode | Route                                       | Description         |
| ------- | ------------------------------------------- | ------------------- |
| GET     | `/api/establishments/{id}/analysis`         | Récupérer l'analyse |
| POST    | `/api/establishments/{id}/analysis/refresh` | Relancer l'analyse  |

---

## Lancer les tests

```bash
php bin/phpunit
```

---

## Présentation en entretien

> "J'ai construit MonAvisPro, un outil de gestion d'avis Google pour les petits commerces. Le problème : les outils existants coûtent 50 à 300€ par mois, hors budget pour un restaurateur ou un artisan. J'ai utilisé Symfony 7, une API REST avec JWT, l'API Google Places, un LLM pour analyser les tendances dans les avis et générer des réponses, Symfony Scheduler pour la surveillance automatique. C'est déployé en prod sur Railway."

---

## Licence

MIT
