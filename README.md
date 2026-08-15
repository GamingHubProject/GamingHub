# Gaming Hub Platform

**v0.1.090** — Standalone modular Laravel platform for game communities.

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

`ServerGroup`, `Map`, `ConfigurationPreset`, `InstalledPackage`, `Page`, and `Theme` stay in
Platform and reference Core's models by foreign key rather than through a relation defined on the
Core side.

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
- `GameExtensionContract` — defines what a real Game Integration package will implement once
  package loading exists (superseded as a registry by `InstalledPackage`, see Manager below)
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
- Core's `ManualProvider` — the bound value is whatever an admin typed in, no external I/O
  involved. Real Connector-backed providers arrived in the section below
- `ServerStatusBlock` reads through the gateway instead of the DB directly — proves the highway
  end-to-end, including what an unbound (`UNSUPPORTED`) capability looks like in the UI

**Manager (integrated)**
- `App\Manager\PackageRegistry` — parses a registry file (`extension_registry.json`/
  `games_registry.json`, the format the `Registry` repo publishes) into `ExtensionDefinition`s —
  just enough to locate and download a package (id, repository, release/checksum asset names)
- `App\Manager\PackageManifest` — a package's own `gaming-hub-extension.json`, shipped inside its
  release zip. This, not the registry, is the authoritative source for what a package actually
  requires — the same way a Composer package declares its own `require` instead of a central index
  declaring it on the package's behalf. A registry entry can go stale relative to what a package
  needs; the manifest inside the release you just downloaded cannot
- `App\Manager\VersionResolver` — checks a `requires` array (normally a manifest's) against
  installed package versions (wraps `composer/semver`)
- `App\Manager\PackageDownloader` + `ChecksumVerifier` — downloads a release zip, verifies it
  against the registry's checksum manifest, and extracts it, refusing to install anything that
  fails verification
- `App\Manager\PackageInstaller` ties it together: fetch registry → find package → download+verify
  → read its manifest → check `requires` against what's already installed → record an
  `InstalledPackage` row (`disabled` by default; admin enables it explicitly)
- `InstalledPackage` model (Extensions → Installed Packages in admin) — bookkeeping for what's
  installed, a Hub Extension (`game_id` null) or Game Integration (`game_id` set). This is state,
  not behavior: installing records what's on disk and downloadable-verified, it does **not** load
  any package PHP code at runtime — that's a separate, harder problem (dynamic package loading)
  this deliberately doesn't attempt yet
- **Browse Registry** page (Extensions → Browse Registry) — fetches a registry live and lists what's
  there with description/category/already-installed version, Install button per row (asks only for
  the exact version — no "latest" guessing). Replaced an earlier version of this that made admins
  type an exact package ID blind into a form with no way to see what existed

**Connectors — first real (non-manual) capability providers**
- Core's `NormalizerContract` + `NormalizerRegistry` — the normalization side of the capability
  highway. Core never fetches raw data itself; it only shapes what Platform hands it. Which
  normalizer applies to a binding is an explicit choice in the binding's config, not auto-inferred
  — the same capability can come from differently-shaped raw payloads (a game's own API vs. a
  hosting panel's generic one)
- `App\Connectors\ConnectorContract` + `RestConnector` (generic authenticated REST — Basic or
  Bearer auth, exact fields per auth style, from the instance's credentials) + `PelicanConnector` +
  `ConnectorRegistry`. Connectors return raw data only — never normalize, never know what a game's
  data means
- **Pelican genuinely needs two separate keys, not one** — verified against real docs and a real
  third-party integration (ClientXCMS's Pelican module), not assumed: an **Application API Key**
  (admin-scoped, `GET /api/application/servers`, sees every server on the panel regardless of
  owner — what real discovery needs) and an optional **Client API Key** (user-scoped,
  `GET /api/client/servers/{id}/resources`, only sees servers that key's own account owns — needed
  for live resource stats, which the Application API doesn't expose at all). `listServers()` uses
  the Application key; `fetch()` uses the Client key; each throws a specific error naming which key
  is missing rather than a generic "no credentials" message
- `ConnectorInstance` model (Capabilities → Connectors in admin) — one credentialed connection to
  an external system, e.g. "this server's Palworld REST API" or "our Pelican panel." Credentials
  are real labeled fields per auth style (Basic username/password, Bearer token, or Pelican's two
  keys) — not a KeyValue field where getting an exact JSON key name wrong silently breaks it
- `App\Capabilities\Providers\ConnectorBackedProvider` — the bridge: implements Core's
  `CapabilityProviderContract`, calls the right Connector via `ConnectorRegistry`, hands the raw
  result to the binding's declared normalizer. Lives in Platform (not Core) since it's the one
  place allowed to touch a Connector
- `App\Normalizers\PalworldServerStatusNormalizer` (real shape: Palworld's own
  `GET /v1/api/metrics`) and `PelicanServerStatusNormalizer` (real shape: Pelican's resources
  response) — both parse actual documented API shapes, not placeholders
- `CapabilityBindingResource` now supports `provider = connector`: pick a Connector, a call config
  (endpoint/method for REST, server identifier for Pelican), and a normalizer — verified end-to-end
  with a fake HTTP layer proving the full path (binding → gateway → router → connector → raw
  payload → normalizer → `CapabilityValue`) for both Palworld-style and Pelican-style calls
- Discovered Pelican servers are now persisted (`ConnectorInstance.discovered_servers`), not just
  shown in a one-time toast — visible as a read-only list on the connector's edit page and reused
  wherever a UUID needs picking

**Providers — a Server's binding to a Connector**
- Core's `Provider` (Milestone 1 scaffolding, never wired to any UI or given real records) is
  redesigned to reference a Platform `ConnectorInstance` by a plain soft `connector_instance_id`
  column instead of duplicating its own `type`/`credentials` — same soft-reference pattern
  `CapabilityBinding` already uses, since Core must never know about Platform's models
- **"Add provider" on a Server's edit page** (`ProvidersRelationManager`) — pick a connection
  (any configured `ConnectorInstance`, "later every other added provider" as more types arrive),
  and if it's Pelican, a second select shows that connector's actual discovered UUIDs to pick
  from — never a blind identifier to type in

**Package lifecycle — made real, not decorative**
- `InstalledPackage.status` now actually gates behavior. `ConnectorBackedProvider` checks (fresh,
  on every resolution — not cached at boot) whether the `InstalledPackage` owning a given
  normalizer is `enabled` before using it; if not, the capability reports `UNAVAILABLE`. Disabling
  `palworld-integration` genuinely stops `server-status` from resolving for any server bound
  through it; re-enabling restores it — proven with a live enable → disable → re-enable test
  against the real gateway, not just a DB flag toggle
- Pelican **"Discover servers"** row action (Capabilities → Connectors) — calls Pelican's real
  `GET /api/client` and lists every server the API key can see (identifier + name), instead of
  admins guessing/hand-typing a `server_identifier`
- A real, live, installable package: `GamingHubProject/Games` (`games_registry.json` +
  `/palworld/gaming-hub-extension.json`, tagged release `v0.1.000`) — genuinely discoverable via
  Browse Registry, downloadable, checksum-verified, and dependency-checked
  (`requires: {"gaming-hub-platform": ">=0.1.070"}`, checked against the real running version).
  It's deliberately **manifest-only** — no PHP code, since dynamic package loading (safely
  activating a downloaded package's own classes without a process restart) still doesn't exist.
  The actual Palworld Connector/Normalizer code stays in Platform, gated on this exact package's
  enabled status. See `Games/palworld/README.md` for the honest breakdown of what's real vs. not

Hub Extensions and the asset file pipeline arrive in later milestones — see
`GAMING_HUB_PLATFORM_ARCHITECTURE.md` for the full roadmap. Dynamic package loading (the mechanism
that would let a downloaded package ship its own real code) and Assets (icons etc.) are the two
deliberate next pieces, neither started.

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
