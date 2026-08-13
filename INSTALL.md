# Install

## Requirements

- Docker + Docker Compose
- No local PHP/Composer/Node install required — everything runs in containers

## 1. Clone & configure

```bash
cd gaming-hub
cp .env.example .env   # already present; edit if you need different credentials
```

The default `.env` is already wired for the bundled `docker-compose.yml` (Postgres on the
`postgres` service, database `gaming_hub`, user `gaming_hub`, password `secret`).

## 2. Build and start the stack

```bash
docker-compose up -d
```

This builds the `app` image (PHP 8.4 CLI + Postgres/GD/Intl/Zip extensions + Node) and starts:

- `app` — Laravel dev server on `http://localhost:8010`
- `postgres` — PostgreSQL 16 on `localhost:5432`

> Port 8010 is used on the host instead of 8000 to avoid clashing with other local services.
> Change the `ports:` mapping in `docker-compose.yml` if you'd like a different port.

## 3. Install dependencies (first run only)

If you're building the image fresh rather than reusing a prebuilt one, install PHP and JS deps:

```bash
docker-compose run --rm app composer install
docker-compose run --rm -e HOME=/tmp app npm install
docker-compose run --rm -e HOME=/tmp app npm run build
```

## 4. Run migrations and seed

```bash
docker-compose run --rm app php artisan migrate --force
docker-compose run --rm app php artisan db:seed --force
```

This creates the `Admin`, `WebEditor`, `ContentEditor`, and `User` roles, plus an admin account:

- **Email:** `admin@local`
- **Password:** `local`

## 5. Access the app

- Public site: `http://localhost:8010`
- Admin panel: `http://localhost:8010/admin`

Log in with `admin@local` / `local`.

## Running tests

Tests run against a dedicated `gaming_hub_test` Postgres database (created once):

```bash
docker-compose exec postgres psql -U gaming_hub -d gaming_hub -c "CREATE DATABASE gaming_hub_test;"
docker-compose run --rm app php artisan test
```

## Production

```bash
cp .env .env.production   # set APP_ENV=production, real DB_PASSWORD, APP_KEY, etc.
docker-compose -f docker-compose.prod.yml up -d
```

The production stack adds an Nginx reverse proxy in front of the app container and exposes
port 80. Set `DB_PASSWORD` in your shell environment or an `.env` file read by Compose before
starting.
