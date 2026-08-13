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

`docker-compose.prod.yml` builds from **`Dockerfile.prod`**, not the dev `Dockerfile`. It's a
self-contained image: `composer install --no-dev` and `npm run build` run at *build time*, so
the container needs no bind-mounted source and no manual setup step after deploy — this matters
for any deployment (Portainer, a bare VPS, CI) where you can't `docker-compose run` one-off
commands against a live host checkout.

On container start, `docker/entrypoint.sh` runs `php artisan migrate --force` automatically,
then execs the configured `php artisan serve` command. If `APP_KEY` isn't set, it generates one
for that run only and prints a warning — set a permanent one (see below) or sessions/encrypted
data won't survive a restart.

```bash
export APP_KEY=$(docker run --rm gaming-hub-app php artisan key:generate --show)
export DB_PASSWORD=<a real password>
export APP_URL=https://your-domain.example

docker-compose -f docker-compose.prod.yml up -d --build
```

The stack adds an Nginx reverse proxy in front of `app` and exposes port 80.

### Deploying via Portainer (Git repository stack)

When you point a Portainer stack at this repo, Portainer clones it and runs
`docker-compose -f docker-compose.prod.yml up` (set the compose path accordingly in the stack
config) against that clone. Because `Dockerfile.prod` bakes in dependencies at build time, no
extra steps are needed inside the container.

- Set `APP_KEY`, `DB_PASSWORD`, and `APP_URL` as environment variables in the Portainer stack
  editor (not committed to git — `.env` is gitignored on purpose).
- Portainer does **not** auto-repull the repo on its own; after pushing new commits, use
  "Pull and redeploy" (or re-create the stack) to pick them up — a stale clone from before code
  existed in the repo is a common cause of "file not found" errors on first deploy.
- If Postgres's default port 5432 (or the app's 8000, if not proxied through Nginx) is already
  used by another stack on the host, remap it in `docker-compose.prod.yml` before deploying.
