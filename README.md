# MonAvisPro

API backend développée avec Symfony 7.4 (PHP 8.4).

## Objectif

Plateforme permettant aux petits commerces (restaurants, artisans, indépendants) de centraliser, analyser et exploiter leurs avis clients.

---

## Problème

Les commerçants reçoivent des avis via plusieurs plateformes (Google, TripAdvisor, etc.) mais manquent de temps et d’outils pour :

- surveiller les nouveaux avis
- répondre rapidement aux avis négatifs
- analyser les retours clients

👉 Résultat : perte de crédibilité et d’opportunités business.

---

## Solution

MonAvisPro permet de :

- centraliser les avis
- identifier les avis critiques
- générer des réponses automatisées (IA)
- analyser les tendances clients

---

## Stack technique

- Symfony 7.4 LTS
- PHP 8.4
- Doctrine ORM
- PostgreSQL
- JWT Authentication
- Docker / Docker Compose
- Symfony Scheduler
- Symfony Mailer
- OpenAI / LLM
- Chart.js

---

## Architecture

Architecture orientée backend moderne :

- `Controller` → endpoints API REST
- `Service` → logique métier
- `Entity` → modèle de données (Doctrine)
- `Scheduler` → tâches automatisées

👉 Conception modulaire permettant d’intégrer plusieurs sources d’avis (Google Business, etc.)

---

## Installation

```bash
git clone https://github.com/DamienCH33/MonAvisPro.git
cd MonAvisPro

composer install

cp .env .env.local

php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

```

## Authentification

API sécurisée avec JWT.

---

## Qualité du code

Le projet suit des standards professionnels :

PHPStan (analyse statique)

PHP CS Fixer (PSR-12)

PHPUnit (tests)

PHPStan
vendor/bin/phpstan analyse src
PHP CS Fixer
vendor/bin/php-cs-fixer fix

---

## Roadmap

-Authentification (JWT)
-Gestion des utilisateurs
-Synchronisation des avis
-Analyse des sentiments
-Génération de réponses automatisées
-Dashboard statistiques

---

## Objectif du projet

Ce projet a pour but de démontrer :

-des compétences backend Symfony
-la conception d’une API REST
-l’intégration de services externes
-une architecture propre et maintenable

---
