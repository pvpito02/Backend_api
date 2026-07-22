# Backend_api — Pointage Mairie de Sandiara

API Laravel pour l’**admin React** et le **mobile Flutter** (agents).

## Stack

- PHP 8.2+
- Laravel 12
- Laravel Sanctum (tokens Bearer)
- MySQL 8 / MariaDB (schéma de référence inclus)

## Prérequis

- PHP 8.2+, Composer
- MySQL / MariaDB
- Extensions PHP courantes (`pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`)

## Installation rapide

```bash
composer install
copy .env.example .env   # Windows
php artisan key:generate
```

Configurer `.env` :

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pointage_mairie
DB_USERNAME=root
DB_PASSWORD=
```

Importer le schéma de référence (Tâche 1 — pas encore de migrations métier) :

```bash
mysql -u root -p < database/schema/pointage_mairie_schema.sql
```

Lancer le serveur :

```bash
php artisan serve
```

## CORS

L’admin React (navigateur) doit être autorisé via CORS. Config : `config/cors.php`.

Variables `.env` :

```env
CORS_ALLOWED_ORIGINS=http://localhost:5173,http://127.0.0.1:5173
CORS_SUPPORTS_CREDENTIALS=false
```

- **Admin (Vite)** : ajoute l’URL exacte (ex. `http://localhost:5173`)
- **Mobile Flutter natif** : pas de CORS navigateur ; appels HTTP directs avec `Authorization: Bearer …`
- Auth prévue en **Bearer tokens** → `CORS_SUPPORTS_CREDENTIALS=false`

Health check :

```text
GET http://127.0.0.1:8000/api/health
```

## Sécurité

- **Ne jamais committer** `.env`, clés, mots de passe, tokens
- `.env` est dans `.gitignore`
- Utiliser `.env.example` comme modèle sans secrets

## Structure utile

| Chemin | Rôle |
|--------|------|
| `routes/api.php` | Routes API |
| `config/sanctum.php` | Config Sanctum |
| `database/schema/pointage_mairie_schema.sql` | Schéma MySQL enrichi (référence) |
| `database/migrations/` | Migrations Laravel (Sanctum déjà publié) |

## Clients

| Client | Auth prévue |
|--------|-------------|
| Mobile Flutter | `Authorization: Bearer {token}` |
| Admin React | idem (tokens Sanctum) |

## Feuille de route (après Tâche 1)

1. Migrations Laravel alignées sur le schéma  
2. Auth login / logout / me (Sanctum)  
3. Modules agents, pointages, demandes, paramètres…

## Licence

Propriétaire — Mairie de Sandiara.
