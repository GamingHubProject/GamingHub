# Gaming Hub Platform

**v0.1.010** — Standalone modular Laravel platform for game communities.

Gaming Hub connects games (Palworld, BDO, ARK, etc.) to the communities playing them. It's a
Docker-based Laravel monolith built to run comfortably on a small VPS — not an Azuriom plugin,
not a microservices stack.

## Stack

- **Framework:** Laravel 11
- **Admin Dashboard:** [Filament](https://filamentphp.com) 3
- **Auth:** Laravel Breeze (Blade stack) + [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission)
- **Database:** PostgreSQL 16
- **Deployment:** Docker (dev + production compose files)

## Features

**Milestone 1 — Platform foundation**
- User accounts (register, login, email verification) via Breeze
- Role-based access control via Spatie Permission (`Admin`, `WebEditor`, `ContentEditor`, `User`)
- Filament admin dashboard at `/admin`
- Domain models: `Game`, `Server`, `Instance`, `Provider`, `Asset`
- A game may have zero servers — publisher-hosted or community-only games are first-class

**Milestone 2 — Game system**
- Context Subject hierarchy: `ServerGroup` (optional server clustering, e.g. an ARK cluster) and
  `Map` (game-scoped, independent of servers — e.g. a BDO grind route with no server)
- Per-game configuration schema (`Game.configuration_schema`) — admins define typed settings
  (decimal/integer/boolean/string, with min/max/default/requiresRestart) directly in the admin UI
- `ConfigurationPreset`s (e.g. hardcore/casual/event) scoped per game
- `Instance` admin form renders typed fields dynamically from its game's schema, with an
  "Apply preset" action that copies a preset's values in before saving
- Minimal `GameExtension` registry + `GameExtensionContract` — tracks known/enabled extensions
  and defines what a real extension package will implement once package loading exists (that
  lifecycle belongs to the separate Manager repo, not Platform)
- Dashboard widgets: platform-wide stat counts and a servers-by-status chart

Hub Extensions, the Capability Highway, Experience/page composition, and the asset file pipeline
arrive in later milestones — see `GAMING_HUB_PLATFORM_ARCHITECTURE.md` for the full roadmap.

## Quick Start (local development)

```bash
docker-compose up
```

Then visit `http://localhost:8010` (public site) or `http://localhost:8010/admin` (admin panel).

Default admin login: `admin@local` / `local`

See [INSTALL.md](INSTALL.md) for full setup steps and [DEVELOPMENT.md](DEVELOPMENT.md) for the
day-to-day developer workflow.

## Production Install (VPS)

One command sets up Docker (if missing), downloads a release, builds the self-contained
production image, and starts it against PostgreSQL — works the same on **Ubuntu/Debian** and
**Arch/CachyOS** (the installer detects `apt` vs `pacman` automatically):

```bash
curl -fsSL https://raw.githubusercontent.com/GamingHubProject/Registry/main/scripts/install-gaming-hub.sh | bash
```

It interactively asks for an install directory, port, database credentials, and timezone, then
offers to configure a domain with automatic HTTPS (via Caddy) and create the first administrator
account. Re-run it any time — the menu also offers a plain **update**, **HTTPS setup**, or
**create/promote an admin account** on an existing install without redoing the full wizard, plus
**uninstall**.

Source: [`GamingHubProject/Registry`](https://github.com/GamingHubProject/Registry/blob/main/scripts/install-gaming-hub.sh)
(reviewing a script before piping it into `bash` is always reasonable — download it first if
you'd rather read it locally).

## File Structure

```
app/Models/              Game, Server, ServerGroup, Instance, Map, Provider, Asset,
                          ConfigurationPreset, GameExtension, User
app/Contracts/           GameExtensionContract (no implementation yet)
app/Filament/Resources/  Admin CRUD resources
app/Filament/Widgets/    Dashboard widgets (platform stats, servers-by-status chart)
database/migrations/     Schema for all models + Filament/Spatie tables
database/seeders/        RoleSeeder + admin test user
tests/Feature/Admin/     Admin panel + resource tests
docker-compose.yml       Dev stack (app + Postgres)
docker-compose.prod.yml  Production stack (self-contained app image + Postgres)
Dockerfile.prod          Production image (composer/npm build baked in, no bind mount)
```

## Versioning

`v{release}.{milestone}.{small-milestone}{hotfix}` — see `VERSION_BUMP_CHECKLIST.md`.
