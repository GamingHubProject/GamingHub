#!/bin/sh
set -e

if [ -z "$APP_KEY" ]; then
    echo "WARNING: APP_KEY is not set. Generating an ephemeral key for this run only."
    echo "Set a permanent APP_KEY in your environment (see INSTALL.md) so sessions"
    echo "survive container restarts: docker run --rm <image> php artisan key:generate --show"
    export APP_KEY="$(php artisan key:generate --show)"
fi

php artisan migrate --force

# Idempotent (artisan skips it if the link already exists) — has to run
# here, not at image build time: app_storage is a runtime-mounted volume
# (see docker-compose*.yml), so anything written to storage/ during the
# build is shadowed by the volume the moment the container actually starts.
php artisan storage:link

exec "$@"
