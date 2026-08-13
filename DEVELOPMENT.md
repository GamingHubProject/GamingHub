# Development

All commands run inside the `app` container so no local PHP/Node install is needed. From the
project root:

```bash
docker-compose run --rm app <command>
```

The examples below omit that prefix for readability — assume every `php artisan` / `composer`
/ `npm` command is run that way (or from inside a shell: `docker-compose exec app bash`).

## Project structure

```
app/Models/                          Eloquent models
app/Filament/Resources/              Admin CRUD resources (list/form/table per model)
app/Providers/Filament/              Panel configuration (AdminPanelProvider)
database/migrations/                 Schema, in chronological order
database/factories/                  Model factories used by tests and seeders
database/seeders/                    RoleSeeder + DatabaseSeeder (admin user)
tests/Feature/Admin/                 Filament resource + dashboard tests
tests/Feature/                       Model and seeder tests
docker-compose.yml                   Dev stack
docker-compose.prod.yml              Prod stack (adds Nginx)
Dockerfile                           PHP 8.4 CLI image with Postgres/Intl/GD/Zip + Node
```

## Adding a model

```bash
php artisan make:model Widget -mf
```

Then fill in the migration's columns, add `$fillable`/`casts()` to the model, and register a
factory definition in `database/factories/WidgetFactory.php`.

## Generating a Filament resource

```bash
php artisan make:filament-resource Widget --generate
```

`--generate` infers form fields and table columns from the database schema. Review the
generated `app/Filament/Resources/WidgetResource.php` and tighten up field types (e.g. swap a
plain `TextInput` for a `Select` on enum-like columns, `KeyValue` for JSON columns).

For a model that lives outside `App\Models` (e.g. Spatie's `Role`), pass
`--model-namespace="Vendor\\Package\\Models"`.

## Running tests

Tests use a dedicated `gaming_hub_test` PostgreSQL database (see INSTALL.md to create it once).

```bash
php artisan test
```

Run a single file or filter by name:

```bash
php artisan test --filter=GameResourceTest
```

## Database migrations

```bash
php artisan migrate              # apply new migrations
php artisan migrate:fresh --seed # drop everything, re-migrate, re-seed (dev only)
php artisan migrate:rollback     # undo the last batch
```

## Common tasks

```bash
php artisan route:list --path=admin   # inspect registered admin routes
php artisan tinker                    # REPL (set -e HOME=/tmp if you hit a psysh permission error)
npm run dev                           # Vite dev server with HMR (run alongside docker-compose up)
npm run build                         # production asset build
composer require <package>            # add a PHP dependency (uses the same PHP 8.4 image)
```

## Notes on the Docker setup

- The image pins PHP 8.4 to match what Composer resolved when this project was scaffolded —
  don't downgrade the base image without also re-resolving `composer.lock`.
- Composer's advisory-blocking feature (`policy.advisories.block`) is disabled in the container's
  global config so `composer require` doesn't hard-fail on known-but-irrelevant advisories in
  transitive dependencies; run `composer audit` periodically instead.
- `npm` commands need `-e HOME=/tmp` (or another writable HOME) when run as the host UID via
  `docker-compose run --user "$(id -u):$(id -g)"`, otherwise npm's cache directory write fails.
