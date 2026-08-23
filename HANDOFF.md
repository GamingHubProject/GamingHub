# Gaming Hub Platform — Handoff

Stand: 2026-08-23. Für die nächste Session/Person gedacht — falls du das liest
und nicht weißt, wo du anfangen sollst: Abschnitt 3 zuerst.

## 1. Projektüberblick

"Gaming Hub Platform" (`GamingHubProject/GamingHub`) ist die Laravel/Filament-
Anwendung mit React-SPA-Frontend. Sie bindet Server-Panels (aktuell: Pelican)
über ein Connector/Capability-System an, zeigt Live-Daten (Status, CPU/RAM,
Spielerzahl) an und lässt Admins Dashboards und Server-Detailseiten aus
Widgets/Cards zusammenstellen. Vier Repos gehören zusammen — siehe Abschnitt 8.

## 2. Was in dieser Session erledigt wurde (chronologisch)

- Admin-Routing-Split: Filament von `/admin` nach `/admin/system`, `/admin`
  frei für eine künftige React-Admin-Oberfläche.
- Dashboard-Widget-System: Server Status Widget (Karte mit Badge/Balken),
  „Create dashboard"-Einstieg für leere Dashboards, echtes
  Drag/Resize-Grid via `react-grid-layout` (vorher: nur Stapel-Layout).
- Connector Instance Edit-Seite: „Test Connection"/„Discover Servers" waren
  nie auf der Edit-Seite verfügbar (nicht durch den Routing-Split
  verursacht — per Git-History verifiziert) — jetzt dort erreichbar.
- Package-Installer (Browse Registry): Versions-Dropdown mit echten
  GitHub-Releases statt Freitext-Eingabe. Dabei einen echten Bug gefunden:
  `APP_VERSION` kam nie im Container an (fehlte in
  `docker-compose.prod.yml`s `environment:`-Liste) → `config('app.version')`
  fiel auf `"dev"` zurück → `composer/semver` warf beim Prüfen von
  Paket-Anforderungen. Gefixt.
- Server Detail Seite: komplett neues, admin-editierbares Karten-Layout
  (`server_layouts`/`server_layout_widgets`, eigene Tabellen — bewusst
  keine Wiederverwendung von `dashboard_widgets`, da unterschiedliches
  Berechtigungsmodell: privat/Owner vs. öffentlich lesbar/Admin-only
  schreibbar). 5 Widget-Typen: Banner, Status, Metrics, Player Count,
  Allocations.
- Zwei Provider-Bugs gefixt: (a) `PollProviders` prüfte „ist irgendein
  Provider fällig" statt „ist ein Provider *für diese Capability* fällig"
  — ein unabhängiger `player-list`-Provider konnte dadurch fälschlich
  `server-status` auf „offline" zwingen. (b) `player-list` wurde im
  Poll-Loop nie abgefragt, obwohl ein Manual-Provider dafür existierte.
- Asset Library (Phase 1): Upload/Browse/Delete für Bilder, serverseitige
  Thumbnails via GD, `AssetPicker`-Komponente, als erste echte Nutzung in
  den Server-Banner eingebaut (Hintergrundbild).
- Versionsschema-Migration von `X.Y.Z` auf `X.Y.ZZZ.NN` — siehe Abschnitt 4.
- Zwei Bugs im Asset-Upload-Flow gefixt — siehe Abschnitt 5 (waren offen,
  sind jetzt beide gefixt und live verifiziert, nicht mehr offen).

## 3. Wie man weiterarbeitet

```bash
cd ~/Documents/ClaudeCode/gaming-hub
docker compose up -d
docker compose exec app php artisan test        # Backend-Tests (aktuell 344)
docker compose exec app bash -c "cd spa && npm test -- --run && npm run build"
```

Produktiv-Deploy (siehe auch Abschnitt 6 — Verzeichnisse NICHT verwechseln):

```bash
cd /opt/gaming-hub
# Dateien aus dem Coder-Arbeitsverzeichnis manuell rüberkopieren (siehe Abschnitt 6)
docker compose -f docker-compose.prod.yml -p gaming-hub-prod build app
docker compose -f docker-compose.prod.yml -p gaming-hub-prod up -d --force-recreate app scheduler
```

## 4. Versions-Stand und Konvention

**Aktueller Tag: `v0.1.006.01`** (GamingHub-Repo, `main`).

Schema: **`X.Y.ZZZ.NN`** = `Release.HugeMilestone.SmallMilestone.Hotfix`

- **X (Release)**: bleibt lange unangetastet.
- **Y (Huge Milestone)**: nur bei wirklich großen Meilensteinen (z.B. „volles
  Admin-Edit-Mode-System fertig", „erste öffentliche Version") —
  **braucht Bestätigung vom User vor dem Bump.**
- **ZZZ (Small Milestone)**: abgeschlossene, bedeutsame Features/Systeme
  (z.B. Asset Library, Dashboard-Grid, Admin-Routing-Split) — drei Stellen,
  damit Platz zum Wachsen bleibt — **braucht Bestätigung vom User vor dem
  Bump.**
- **NN (Hotfix)**: jede normale Fix/kleine Änderung — **frei bumpbar, keine
  Bestätigung nötig.**

Migration: `v0.6.000` (altes Schema) → `v0.1.006.00` (neues Schema, gleicher
Commit, mit User bestätigt) → `v0.1.006.01` (erster Hotfix, JPEG/WebP + CSS
Fix).

**Wichtige Falle:** GitHub sortiert seine `/tags`-API **lexikographisch nach
Tag-Namen**, nicht chronologisch und nicht numerisch. Ein rohes
`gh api .../tags --jq '.[0].name'` liefert aktuell **`v0.5.002`** (altes
Schema, sortiert lexikographisch höher) — **nicht** den echten aktuellen
Tag. Der Installer hat dafür einen eigenen `latest_tag()`-Mechanismus (siehe
Abschnitt 7); für eine manuelle Prüfung:

```bash
gh api repos/GamingHubProject/GamingHub/tags --jq '.[].name' \
  | grep -E '^v[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$' | sort -V | tail -1
```

## 5. Bekannte offene Bugs

**Aktuell keine bekannten offenen Bugs.** Die beiden zuletzt gemeldeten
(WebP/JPEG-Upload-Fehler — fehlende GD-Unterstützung im Docker-Image; und
das volle-Breite-Styling des Banner-Status-Badges — fehlendes
`alignItems` in einem Flex-Container) sind beide gefixt, live gegen die
echte Produktivinstanz verifiziert und deployed (Tag `v0.1.006.01`).

Falls hier trotzdem etwas als „offen" auftaucht, das oben nicht steht:
neu, seit dem 23.08. entstanden — bitte diesen Abschnitt aktuell halten.

## 6. Deployment-Setup — Verzeichnisse nicht verwechseln

Zwei komplett getrennte Verzeichnisse, diese Session mehrfach verwechselt:

- **`~/Documents/ClaudeCode/gaming-hub`** — das Coder-Arbeitsverzeichnis
  (dev container, hier wird Code geschrieben/getestet, git-Repo mit `.git`).
- **`/opt/gaming-hub`** — die echte Produktivinstanz (`gaming-hub-prod-*`
  Container, läuft auf Port 8087, **kein** `.git` — reiner Datei-Snapshot,
  wird manuell synchronisiert, nicht per `git pull`).

Es gibt **keine automatische Synchronisation** zwischen beiden. Jede Änderung
muss nach `/opt/gaming-hub` kopiert werden (einzelne Dateien `cp`, nicht
`rsync -a --delete` — das würde `.env`, `storage/`, hochgeladene Assets
etc. zerstören), bevor Image-Rebuild + Container-Neustart einen Effekt
zeigen. `/opt/gaming-hub` enthält echte Nutzerdaten (Rose's echter Account,
echte Provider/Server-Konfiguration, hochgeladene Assets) — beim Testen
immer Wegwerf-Accounts verwenden, nie echte Daten anfassen, und danach
aufräumen.

## 7. Installer-Besonderheiten

Repo: `gaming-hub-registry`, Script: `scripts/install-gaming-hub.sh`
(aktuell `INSTALLER_VERSION="0.1.003"`).

- **Kein separates Dev-/Prod-Installer-Script** — ein Script für beides,
  gesteuert über Prompts (Install-Verzeichnis, HTTPS ja/nein, etc.). Der
  Unterschied Dev-vs-Prod liegt eher in den beiden Dockerfiles
  (`Dockerfile` fürs Coder-Arbeitsverzeichnis mit Bind-Mount, `Dockerfile.prod`
  self-contained ohne Bind-Mount) als im Installer selbst.
- **„latest"-Auflösung**: `latest_tag()` in `install-gaming-hub.sh` — bevorzugt
  jeden 4-Segment-Tag (neues Schema) über jeden 3-Segment-Tag (altes Schema),
  `sort -V` innerhalb jeder Gruppe. Notwendig geworden durch die
  Versions-Migration (siehe Abschnitt 4) — GitHubs rohe Tag-Reihenfolge ist
  dafür nicht verlässlich.
- **GitHub Compare API** (`show_version_diff()`): zeigt beim Update die
  echten Commit-Unterschiede zwischen installierter und Ziel-Version — nutzt
  `/repos/.../compare/{previous}...{target}` (`ahead_by`, Commit-Messages),
  komplett Versions-String-unabhängig, kein eigenes Parsing.
- **`.gaming-hub-installed-ref`**: Marker-Datei im Install-Verzeichnis, hält
  fest, welcher Ref zuletzt erfolgreich installiert wurde (für den Vergleich
  oben). Wird von `record_installed_ref()` geschrieben. **Wichtig:** diese
  Datei wird nur geschrieben, wenn der *echte Installer* läuft — manuelles
  Deployen (Abschnitt 6) aktualisiert sie nicht automatisch; ggf. von Hand
  nachziehen (`echo "vX.Y.ZZZ.NN" > /opt/gaming-hub/.gaming-hub-installed-ref`),
  sonst zeigt ein späteres „Update" einen falschen Diff.

## 8. Alle Repos

| Repo | GitHub | main HEAD (Kurzfassung) | Aktuellster Tag |
|---|---|---|---|
| `gaming-hub` | GamingHubProject/GamingHub | `f44cdfd` — Fix JPEG/WebP uploads (GD build gap) and banner status badge CSS | `v0.1.006.01` |
| `gaming-hub-core` | GamingHubProject/Core | `ce3ba2a` — Priority 13: Pelican architecture + per-provider polling cadence (schema) | `v0.1.030` (main ist 9 Commits weiter, ungetaggt) |
| `gaming-hub-registry` | GamingHubProject/Registry | `7748eaa` — Fix 'latest' resolution for the new X.Y.ZZZ.NN version scheme | kein Tagging (Installer wird immer von `main` per raw URL geladen) |
| `gaming-hub-basicconnectors` | GamingHubProject/BasicConnectors | `a7d8d4f` — Priority 13: unified status precedence, supported_features, allocations | `v0.1.3` (main = Tag, aktuell) |

`gaming-hub-core` hat 9 ungetaggte Commits auf `main` — falls das für die
nächste Session relevant wird (z.B. neue Composer-Version ziehen), vorher
prüfen ob die als eigener Core-Release getaggt werden müssen.
