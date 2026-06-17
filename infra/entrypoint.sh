#!/bin/sh
set -eu

# APP_KEY is a real secret in production (set via `fly secrets set APP_KEY=...`).
# Fail loudly if it is missing rather than booting with a broken encrypter; for
# local/testing, mint an ephemeral key so the image runs with zero setup and no
# secret is ever committed.
if [ -z "${APP_KEY:-}" ]; then
  case "${APP_ENV:-production}" in
    local | testing)
      export APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
      echo "entrypoint: APP_KEY unset — generated an ephemeral key for ${APP_ENV}." >&2
      ;;
    *)
      echo "entrypoint: APP_KEY is required in ${APP_ENV:-production} (e.g. fly secrets set APP_KEY=...)." >&2
      exit 1
      ;;
  esac
fi

# The SQLite file lives on the mounted volume, not in the image, so the season
# survives redeploys. Create its directory and file on first boot, then fold the schema.
if [ -n "${DB_DATABASE:-}" ]; then
  mkdir -p "$(dirname "$DB_DATABASE")"
  [ -f "$DB_DATABASE" ] || touch "$DB_DATABASE"
fi

php artisan migrate --force
php artisan config:cache

exec frankenphp run --config /etc/caddy/Caddyfile --adapter caddyfile
