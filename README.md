# Gaming Hub Platform

**v0.1.042** — Standalone modular Laravel platform for game communities.

Gaming Hub connects games (Palworld, BDO, ARK, etc.) to the communities playing them. It's a
Docker-based Laravel monolith built to run comfortably on a small VPS — not an Azuriom plugin,
not a microservices stack.

## Architecture: Platform + Core

Domain models and capability decisions live in a separate, independently-versioned package —
[`GamingHubProject/Core`](https://github.com/GamingHubProject/Core) — required via Composer
(`gaminghubproject/core`), not in this repo. Platform integrates Manager (package discovery) and
Panel (connector routing) directly; Core owns `Game`/`Server`/`Instance`/`Provider`,
`CapabilityBinding` (the capability routing/definition data), `CapabilityRouter` (decides which
provider serves a capability), and normalization — and never composes UI, applies themes, manages
assets, or speaks to connectors directly. That split exists so Core can ship updates independently
without risking the monolith.

Platform's `CapabilityGateway` (`app/Capabilities/CapabilityGateway.php`) is the actual entry
point Extensions call — it acts as Panel: orchestration, caching, and (once real Connectors exist)
invoking them. It asks Core's `CapabilityRouter` for the routing decision and delegates
normalization to whichever provider Core resolves; `ManualProvider` (no external I/O) lives
entirely in Core since it never speaks to a connector.

`ServerGroup`, `Map`, `ConfigurationPreset`, `GameExtension`, `Page`, and `Theme` stay in Platform
and reference Core's models by foreign key rather than through a relation defined on the Core
side.

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

**Milestone 3 — Experience/page composition**
- `Page` model: admin-composed pages (title, slug, optional game scope, draft/published status)
  built from an ordered list of blocks — no code required to assemble one
- `BlockRegistry` — the one registry for page-builder blocks; block classes implement
  `BlockContract` (id, label, Filament config schema, render) and register themselves in
  `AppServiceProvider`. Hub Extensions will register their own blocks here later the same way
- Built-in blocks: Hero, Rich Text, Games List, Server Status
- `Theme` model with a `platform → game → server` hierarchy — each level only needs to set the
  design tokens it wants to override, resolved and merged by `ThemeResolver`; built-in blocks
  render using the resolved `--color-primary` CSS variable
- Public rendering at `/p/{slug}` — renders a published page's blocks in order with its resolved
  theme tokens applied as CSS variables

**Milestone 4 — Capability Highway**
- Platform's `CapabilityGateway` — the single entry point for reading a capability
  (`get`/`inspect`/`probe`), with distinct failure states (`OK`, `UNSUPPORTED`, `UNAVAILABLE`,
  `STALE`) and a freshness-aware cache. `inspect()` is metadata-only and never fetches; `probe()`
  is an explicit runtime call
- Core's `CapabilityRouter` — the one registry of capability providers, and resolves a
  `(capability, subject)` pair to its `CapabilityBinding`
- Core's `CapabilityBinding` — binds a capability to a Context Subject (Game/Server/Instance/Map,
  via a morph map registered by Platform) with a named provider
- Core's `ManualProvider` — the only provider that exists so far: the bound value is whatever an
  admin typed in, no external I/O involved. A real Connector-backed provider (Pelican, RCON, …)
  will split differently — Panel invokes the connector, Core normalizes the raw payload — since
  Core never speaks to a connector directly
- `ServerStatusBlock` reads through the gateway instead of the DB directly — proves the highway
  end-to-end, including what an unbound (`UNSUPPORTED`) capability looks like in the UI

Hub Extensions, real Connector packages, and the asset file pipeline arrive in later milestones —
see `GAMING_HUB_PLATFORM_ARCHITECTURE.md` for the full roadmap.

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
